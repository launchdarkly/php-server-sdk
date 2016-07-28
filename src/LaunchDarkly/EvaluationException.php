<?php
/**
 * Created by IntelliJ IDEA.
 * User: dan
 * Date: 7/28/16
 * Time: 11:20 AM
 */

namespace LaunchDarkly;


use Exception;

class EvaluationException extends Exception {
    /**
     * EvaluationException constructor.
     * @param string $message
     */
    public function __construct($message) {
        parent::__construct($message);
    }
}