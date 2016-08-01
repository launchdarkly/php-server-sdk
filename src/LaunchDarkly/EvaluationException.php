<?php
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