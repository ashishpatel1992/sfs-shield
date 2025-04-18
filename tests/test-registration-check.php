<?php
namespace StopForumSpam\Tests;

use StopForumSpam\RegistrationCheck;
use PHPUnit\Framework\TestCase;

class RegistrationCheckTest extends TestCase {
    public function test_get_user_ip() {
        $plugin = new RegistrationCheck();
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $ip = $plugin->get_user_ip();
        $this->assertEquals('192.168.1.1', $ip);
    }
}