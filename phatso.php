<?php
/**
 * Phatso - A PHP Micro Framework
 * Copyright (C) 2008, Judd Vinet <jvinet@zeroflux.org>
 * 
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 * 
 * (1) The above copyright notice and this permission notice shall be
 *     included in all copies or substantial portions of the Software.
 * (2) Except as contained in this notice, the name(s) of the above
 *     copyright holders shall not be used in advertising or otherwise
 *     to promote the sale, use or other dealings in this Software
 *     without prior written authorization.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 *
 *
 * Version 0.1 :: 2008-10-03
 *   - initial release
 * Version 0.2 :: 2009-04-30
 *   - optimizations (Woody Gilk)
 *   - auto-detect base web root for relative URLs
 * Version 0.2.1 :: 2009-05-31
 *   - bug reported by Sebastien Duquette
 * Version 0.3
 *   - improved run() to handle more cases (Till Theis)
 *
 */

function debug($arg) {
	$args = func_get_args();
	echo '<pre>';
	foreach($args as $arg) {
		echo '(', gettype($arg), ') ', print_r($arg, TRUE)."<br/>\n";
	}
	echo '</pre>';
}

define('PHATSO_VERSION', '0.3');

class Phatso
{
	var $template_layout = 'layout.php';
	var $template_vars   = array();
	var $web_root        = '';

	/**
	 * Dispatch web request to correct function, as defined by
	 * URL route array.
	 */
	function run($urls) {
		$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		$this->web_root = $_SERVER['SCRIPT_NAME'];

		if(strpos($request, $this->web_root) !== 0) {
			$this->web_root = dirname($this->web_root);
			if(strpos($request, $this->web_root) !== 0) {
				$this->web_root = '';
			}
		}

		$ctrl = substr($request, strlen($this->web_root));
		$ctrl = rtrim($ctrl, '/') . '/';
		if ($ctrl{0} !== '/') {
			$ctrl = "/$ctrl";
		}

		$this->web_root = rtrim($this->web_root, '/') . '/';
		echo "ctrl: $ctrl<br>\n";
		echo "root: " . $this->web_root . "<br>\n";

		$action = '';
		$params = array();
		foreach($urls as $request=>$route) {
			if(preg_match('#^'.$request.'$#', $ctrl, $matches)) {
				$action = $route;
				if(!empty($matches[1])) {
					$params = explode('/', trim($matches[1], '/'));
				}
				break;
			}
		}

		if(!function_exists("exec_{$action}")) {
			die("404");
			$this->status('404', 'File not found');
		}
		@call_user_func("exec_{$action}", &$this, $params);
	}

	/**
	 * Set HTTP status code and exit.
	 */
	function status($code, $msg) {
		header("{$_SERVER['SERVER_PROTOCOL']} $code");
		die($msg);
	}

	/**
	 * Redirect to a new URL
	 */
	function redirect($path) {
		header('Location: ' . $this->web_root . $path);
		die;
	}

	/**
	 * Set a template variable.
	 */
	function set($name, $val) {
		$this->template_vars[$name] = $val;
	}

	/**
	 * Render a template and return the content.
	 */
	function fetch($template_filename, $vars=array())
	{
		$vars = array_merge($this->template_vars, $vars);
		ob_start();
		extract($vars, EXTR_SKIP);
		require 'templates/'.$template_filename;
		return str_replace('/.../', $this->web_root, ob_get_clean());
	}

	/**
	 * Render a template (with optional layout) and send the
	 * content to the browser.
	 */
	function render($filename, $vars=array(), $layout='')
	{
		if(empty($layout)) $layout = $this->template_layout;
		if($layout) {
			$vars['CONTENT_FOR_LAYOUT'] = $this->fetch($filename, $vars);
			$filename = $layout;
		}
		echo $this->fetch($filename, $vars);
	}
}
