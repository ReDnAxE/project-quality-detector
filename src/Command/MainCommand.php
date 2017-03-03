<?php
/*
 * This file is part of project-quality-detector.
 *
 * (c) Alexandre GESLIN <alexandre@gesl.in>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ProjectQualityDetector\Command;

use ProjectQualityDetector\Application\UIHelper;
use ProjectQualityDetector\Exception\RuleViolationException;
use ProjectQualityDetector\Loader\RulesLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Main command
 */
class MainCommand extends Command
{
    const SUCCESS_EXIT   = 0;
    const FAILURE_EXIT   = 1;

    protected function configure()
    {
        $this->setName('run')
            ->setDescription('The Project quality tool')
            ->addArgument('applicationType', InputArgument::REQUIRED, 'The application type (symfony or angularjs, etc...)')
            ->addOption('baseDir', '-b', InputOption::VALUE_REQUIRED, 'Change the base directory of application')
            ->addOption('configFile', '-c', InputOption::VALUE_REQUIRED, 'Change the base directory of application');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $exitCode = $this::SUCCESS_EXIT;

        $applicationType = $input->getArgument('applicationType');
        $baseDir = $input->getOption('baseDir') ? $input->getOption('baseDir') : getcwd();
        $configFile = $input->getOption('configFile') ? getcwd() . '/' . $input->getOption('configFile') : $this->getConfigFile(); //TODO: check

        $rulesLoader = new RulesLoader();

        try {
            $rules = $rulesLoader->load($configFile, $applicationType, $baseDir);
        } catch (\InvalidArgumentException $e) {
            UIHelper::displayException($e, $output);

            return $this::FAILURE_EXIT;
        }

        UIHelper::displayStartingBlock($output, $configFile);

        foreach ($rules as $rule) {
            try {
                $rule->evaluate();
                UIHelper::displayRuleSuccess($rule, $output);
            } catch (RuleViolationException $e) {
                UIHelper::displayRuleViolation($e, $output);
                $exitCode = $this::FAILURE_EXIT;
            }
        }

        return $exitCode;
    }

    /**
     * @return string
     */
    protected function getConfigFile()
    {
        $configsToSearch = [
            getcwd() . '/pqd.yml',
            __DIR__ . '/../../pqd.yml'
        ];

        return (file_exists($configsToSearch[0])) ? $configsToSearch[0] : $configsToSearch[1];
    }
}