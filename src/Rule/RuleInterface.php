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

use ProjectQualityInspector\Exception\RuleViolationException;

/**
 * Interface RuleInterface
 *
 * @package ProjectQualityInspector\Rule
 */
interface RuleInterface
{
    /**
     * RuleInterface constructor.
     *
     * @param array $config
     * @param string $baseDir
     */
    public function __construct(array $config, $baseDir);

    /**
     * @return mixed
     *
     * @throws RuleViolationException|\InvalidArgumentException
     */
    public function evaluate();

    /**
     * @return array
     */
    public function getAssertions();

    /**
     * @return array
     */
    public function getConfig();

    /**
     * @return string
     */
    public static function getRuleName();
}