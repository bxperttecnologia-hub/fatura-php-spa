<?php
class HttpException extends Exception {
    public function __construct(public int $status, string $message, public array $errors = []) {
        parent::__construct($message);
    }
}
