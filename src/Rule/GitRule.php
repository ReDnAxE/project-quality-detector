<?php
/*
 * This file is part of project-quality-inspector.
 *
 * (c) Alexandre GESLIN <alexandre@gesl.in>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ProjectQualityInspector\Rule;

use ProjectQualityInspector\Application\ProcessHelper;
use ProjectQualityInspector\Exception\ExpectationFailedException;

/**
 * Class GitRule
 *
 * @package ProjectQualityInspector\Rule
 */
class GitRule extends AbstractRule
{
    private $commitFormat = '%H|%ci|%cr|%an';
    private $commitFormatKeys = ['commitHash', 'committerDate', 'relativeCommitterDate', 'authorName', 'branchName'];

    public function __construct(array $config, $baseDir)
    {
        parent::__construct($config, $baseDir);
    }

    /**
     * @inheritdoc
     */
    public function evaluate()
    {
        $expectationsFailedExceptions = [];
        $stableBranches = $this->getStableBranches();

        try {
            $this->expectsNoMergedBranches($stableBranches, $this->config['threshold-too-many-merged-branches']);
            $this->addAssertion('expectsNoMergedBranches');
        } catch (ExpectationFailedException $e) {
            $expectationsFailedExceptions[] = $e;
            $this->addAssertion('expectsNoMergedBranches', [['message' => $e->getMessage() . $e->getReason(), 'type' => 'expectsNoMergedBranches']]);
        }

        $notMergedBranchesInfo = $this->listMergedOrNotMergedBranches($stableBranches, false);

        foreach ($notMergedBranchesInfo as $notMergedBranchInfo) {
            try {
                $this->expectsBranchNotTooBehind($notMergedBranchInfo, $stableBranches);
                $this->addAssertion($notMergedBranchInfo['branchName']);
            } catch (ExpectationFailedException $e) {
                $expectationsFailedExceptions[] = $e;
                $this->addAssertion($notMergedBranchInfo['branchName'], [['message' => $e->getMessage() . $e->getReason(), 'type' => 'expectsBranchNotTooBehind']]);
            }
        }

        if (count($expectationsFailedExceptions)) {
            $this->throwRuleViolationException($expectationsFailedExceptions);
        }
    }

    /**
     * @inheritdoc
     */
    public static function getGroups()
    {
        return array_merge(parent::getGroups(), ['git']);
    }

    /**
     * @param array $stableBranches
     * @param int $threshold
     *
     * @throws ExpectationFailedException
     */
    private function expectsNoMergedBranches(array $stableBranches, $threshold)
    {
        if ($mergedBranches = $this->listMergedOrNotMergedBranches($stableBranches, true)) {
            if (count($mergedBranches) >= $threshold) {
                $message = sprintf('there is too much remaining merged branches (%s) : %s', count($mergedBranches), $this->stringifyCommitArrays($mergedBranches));
                throw new ExpectationFailedException($mergedBranches, $message);
            }
        }
    }

    /**
     * Compare stable branch first commit after common ancestor of not merged branch and stable branch, and expects days delay do not override limit
     *
     * @param array $notMergedBranchInfo
     * @param array $stableBranches
     *
     * @throws ExpectationFailedException
     */
    private function expectsBranchNotTooBehind(array $notMergedBranchInfo, array $stableBranches)
    {
        foreach ($stableBranches as $stableBranch) {
            $failed = false;
            $lrAheadCommitsCount = $this->getLeftRightAheadCommitsCountAfterMergeBase($stableBranch, $notMergedBranchInfo['branchName']);

            if ($lrAheadCommitsCount[$stableBranch] > 0) {
                $commonAncestorCommitInfo = $this->getMergeBaseCommit($notMergedBranchInfo['branchName'], $stableBranch);
                $stableBranchLastCommitInfo = $this->getBranchLastCommitInfo($stableBranch);

                if ($lrAheadCommitsCount[$stableBranch] >= (int)$this->config['threshold-commits-behind']) {
                    $failed = true;
                }

                $interval = $this->getCommitInfosDatesDiff($commonAncestorCommitInfo, $stableBranchLastCommitInfo);
                if ((int)$interval->format('%r%a') >= (int)$this->config['threshold-days-behind']) {
                    $failed = true;
                }

                if ($failed) {
                    $message = sprintf('The branch <fg=green>%s</> is behind <fg=green>%s</> by %s commits spread through %s days.', $notMergedBranchInfo['branchName'], $stableBranch, $lrAheadCommitsCount[$stableBranch], (int)$interval->format('%r%a'));
                    $message .= sprintf(' <fg=green>%s</> should update the branch %s', $notMergedBranchInfo['authorName'], $notMergedBranchInfo['branchName']);
                    throw new ExpectationFailedException($notMergedBranchInfo, $message);
                }
            }
        }
    }

    /**
     * Get merged/not merged branches compared to $stablesBranches.
     * If there is multiple stable branches, and $merged = true : list branches that are merged in at least one of the stable branches
     * If there is multiple stable branches, and $merged = false : list branches that are not merged for any of the stable branches
     *
     * @param array $stableBranches
     * @param bool $merged
     * @return array
     */
    private function listMergedOrNotMergedBranches(array $stableBranches, $merged = true)
    {
        $branches = [];
        $mergedOption = ($merged) ? '--merged' : '--no-merged';

        foreach ($stableBranches as $stableBranch) {
            $result = ProcessHelper::execute(sprintf('for branch in `git branch -r %s %s | grep -v HEAD | grep -ve "%s" | grep -ve "%s"`; do echo `git show --format="%s" $branch | head -n 1`\|$branch; done | sort -r', $mergedOption, $stableBranch, $this->getBranchesRegex('stable-branches-regex'), $this->getBranchesRegex('ignored-branches-regex'), $this->commitFormat), $this->baseDir);
            $branches[$stableBranch] = $this->explodeCommitsArrays($result);
            if ($merged) {
                /*foreach ($branches[$stableBranch] as $branchHash => $mergedBranchCommit) {
                    //TODO: get stableBranch getBranchFirstCommitInfo
                    //$branches[$stableBranch][$branchHash]['mergeCommit'] = $this->getBranchFirstCommitInfo($mergedBranchCommit['branchName'], $stableBranch);
                }
                print_r($branches);*/
            }
        }

        if (count($branches) > 1) {
            $branches = ($merged) ? call_user_func_array('array_merge', $branches) : call_user_func_array('array_intersect_key', $branches);
        } else {
            $branches = $branches[$stableBranches[0]];
        }

        return $branches;
    }

    /**
     * Get numbers of commits after common ancestor for $branchLeft and $branchRight (ex: $branchLeft => 2,  $branchRight => 5)
     *
     * @param $branchLeft
     * @param $branchRight
     * @return array
     */
    private function getLeftRightAheadCommitsCountAfterMergeBase($branchLeft, $branchRight)
    {
        $result = ProcessHelper::execute(sprintf('git rev-list --left-right --count %s...%s', $branchLeft, $branchRight), $this->baseDir);
        $result = explode("\t", $result[0]);

        $result = [
            $branchLeft => $result[0],
            $branchRight => $result[1]
        ];

        return $result;
    }

    /**
     * Get common ancestor commit
     *
     * @param string $branchLeft
     * @param string $branchRight
     * @return array
     */
    private function getMergeBaseCommit($branchLeft, $branchRight)
    {
        $commitInfo = null;
        $result = ProcessHelper::execute(sprintf('git merge-base %s %s', $branchLeft, $branchRight), $this->baseDir);

        if (count($result)) {
            $commitInfo = $this->getBranchLastCommitInfo($result[0]);
        }

        return $commitInfo;
    }

    /**
     * Get first commit of $branch after common ancestor commit of $baseBranch and $branch
     *
     * @param string $baseBranch
     * @param string $branch
     * @return array
     */
    private function getBranchFirstCommitInfo($baseBranch, $branch)
    {
        $branchInfo = null;
        $result = ProcessHelper::execute(sprintf('git log --format="%s" %s..%s | tail -1', $this->commitFormat, $baseBranch, $branch), $this->baseDir);
        if (count($result)) {
            $explodedCommit = explode('|', $result[0]);
            $branchInfo = array_combine(array_slice($this->commitFormatKeys, 0, count($explodedCommit)), $explodedCommit);
            $branchInfo[] = $branch;
        }

        return $branchInfo;
    }

    /**
     * @param $branchInfoLeft
     * @param $branchInfoRight
     * @return bool|\DateInterval
     */
    private function getCommitInfosDatesDiff($branchInfoLeft, $branchInfoRight)
    {
        $format = 'Y-m-d H:i:s O';
        $dateLeft = \DateTime::createFromFormat($format, $branchInfoLeft['committerDate']);
        $dateRight = \DateTime::createFromFormat($format, $branchInfoRight['committerDate']);

        return $dateLeft->diff($dateRight);
    }

    /**
     * @param $branch
     * @return array
     */
    private function getBranchLastCommitInfo($branch)
    {
        $result = ProcessHelper::execute(sprintf('git show --format="%s" %s | head -n 1', $this->commitFormat, $branch), $this->baseDir);
        $explodedCommit = explode('|', $result[0]);
        $branchInfo = array_combine(array_slice($this->commitFormatKeys, 0, count($explodedCommit)), $explodedCommit);
        $branchInfo[] = $branch;

        return $branchInfo;
    }

    /**
     * @return array
     */
    private function getStableBranches()
    {
        $result = ProcessHelper::execute(sprintf('git branch -r | grep -e "%s"', $this->getBranchesRegex('stable-branches-regex')), $this->baseDir);

        return array_map("trim", $result);
    }

    /**
     * @param string $configKey
     * @return string
     */
    private function getBranchesRegex($configKey)
    {
        $branchesRegex = ['^$'];

        if (is_array($this->config[$configKey]) && count($this->config[$configKey])) {
            $branchesRegex = array_map(function ($element) {
                return '\(^[ ]*'.$element.'$\)';
            }, $this->config[$configKey]);
        }

        return implode('\|', $branchesRegex);
    }

    /**
     * @param array $commits
     * @return string
     */
    private function stringifyCommitArrays(array $commits)
    {
        $commits = array_map(function($commit) {
            return sprintf('%s - %s by %s', $commit['branchName'], $commit['relativeCommitterDate'], $commit['authorName']);
        }, $commits);
        return "\n\t" . implode("\n\t", $commits);
    }

    /**
     * @param  array  $commits
     * @return array
     */
    private function explodeCommitsArrays(array $commits)
    {
          $explodedCommits = [];

          foreach ($commits as $commit) {
              $explodedCommit = explode('|', $commit);
              $explodedCommit = array_combine(array_slice($this->commitFormatKeys, 0, count($explodedCommit)), $explodedCommit);
              $explodedCommits[$explodedCommit['commitHash']] = $explodedCommit;
          }

          return $explodedCommits;
    }
}