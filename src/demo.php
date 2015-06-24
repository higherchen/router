<?php
require __DIR__ . '/Router.php';

use higherchen\router\Router;

// Router::get('/hello', function () {
// 	echo "hello world!!";
// });

// Router::before('GET|POST', '/admin/2.*', function () {
// 	echo "Please login first";
// 	exit();
// });

// Router::get('/hello/world', function () {
// 	echo "hello world!";
// });

// Router::get('/admin/1', 'sample@admin');

// Router::set404('sample@notfound');

// Router::get('/(\w+)/(\w+)/.*', function ($controller, $action) {
// 	echo $controller . ":" . $action;
// });

// Router::run(function () {
// 	echo "Successfully worked! ";
// });

Router::setByIniFile(__DIR__ . '/../vendor/higherchen/router/src/sample.ini');

class sample {
	function auth() {
		echo "Please login first! ";
		exit();
	}
	function admin() {
		echo "You are accessing admin module! ";
	}
	function hello() {
		echo "Hello! ";
	}
	function world() {
		echo "World! ";
	}
	function notfound() {
		echo "Not found! ";
	}
	function success() {
		echo "Successfully worked! ";
	}
	function n() {
		echo "print new!";
	}
}