<?php
/**
 * The Plugin Task handles creating an empty plugin, ready to be used
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         CakePHP(tm) v 1.2
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

App::uses('AppShell', 'Console/Command');
App::uses('File', 'Utility');
App::uses('Folder', 'Utility');

/**
 * The Plugin Task handles creating an empty plugin, ready to be used
 *
 * @package       Cake.Console.Command.Task
 */
class PluginTask extends AppShell {

/**
 * path to plugins directory
 *
 * @var array
 */
	public $path = null;

/**
 * initialize
 *
 * @return void
 */
	public function initialize() {
		$this->path = current(App::path('plugins'));
	}

/**
 * Execution method always used for tasks
 *
 * @return void
 */
	public function execute() {
		if (isset($this->args[0])) {
			$plugin = Inflector::camelize($this->args[0]);
			$pluginPath = $this->_pluginPath($plugin);
			if (is_dir($pluginPath)) {
				$this->out(__d('cake_console', 'Plugin: %s', $plugin));
				$this->out(__d('cake_console', 'Path: %s', $pluginPath));
			} else {
				$this->_interactive($plugin);
			}
		} else {
			return $this->_interactive();
		}
	}

/**
 * Interactive interface
 *
 * @param string $plugin
 * @return void
 */
	protected function _interactive($plugin = null) {
		while ($plugin === null) {
			$plugin = $this->in(__d('cake_console', 'Enter the name of the plugin in CamelCase format'));
		}

		if (!$this->bake($plugin)) {
			$this->error(__d('cake_console', "An error occurred trying to bake: %s in %s", $plugin, $this->path . $plugin));
		}
	}

/**
 * Bake the plugin, create directories and files
 *
 * @param string $plugin Name of the plugin in CamelCased format
 * @return boolean
 */
	public function bake($plugin, $skel = null, $skip = array('empty')) {
		$pathOptions = App::path('plugins');
		if (count($pathOptions) > 1) {
			$this->findPath($pathOptions);
		}

		if (!$skel && !empty($this->params['skel'])) {
			$skel = $this->params['skel'];
		}
		while (!$skel) {
			$skel = $this->in(
				__d('cake_console', "What is the path to the directory layout you wish to copy?"),
				null,
				CAKE . 'Console' . DS . 'Templates' . DS . 'skel'
			);
			if (!$skel) {
				$this->err(__d('cake_console', 'The directory path you supplied was empty. Please try again.'));
			} else {
				while (is_dir($skel) === false) {
					$skel = $this->in(
						__d('cake_console', 'Directory path does not exist please choose another:'),
						null,
						CAKE . 'Console' . DS . 'Templates' . DS . 'skel'
					);
				}
			}
		}

		$path = $this->path . $plugin;

		$this->hr();
		$this->out(__d('cake_console', "<info>Plugin Name:</info> %s", $plugin));
		$this->out(__d('cake_console', '<info>Skel Directory</info>: %s', $skel));
		$this->out(__d('cake_console', '<info>Will be copied to</info>: %s', $path));
		$this->hr();
		$looksGood = $this->in(__d('cake_console', 'Look okay?'), array('y', 'n', 'q'), 'y');

		switch (strtolower($looksGood)) {
			case 'y':
				$Folder = new Folder($skel);
				if (!empty($this->params['empty'])) {
					$skip = array();
				}

				if ($Folder->copy(array('to' => $path, 'skip' => $skip))) {
					$this->hr();
					$this->out(__d('cake_console', '<success>Created:</success> %s in %s', $plugin, $this->path));
					$this->hr();
				} else {
					$this->err(__d('cake_console', "<error>Could not create</error> '%s' properly.", $plugin));
					return false;
				}

				foreach ($Folder->messages() as $message) {
					$this->out(String::wrap(' * ' . $message), 1, Shell::VERBOSE);
				}

				$controllerFileName = $plugin . 'AppController.php';

				$out = "<?php\n\n";
				$out .= "class {$plugin}AppController extends AppController {\n\n";
				$out .= "}\n\n";
				$this->createFile($path . DS . 'Controller' . DS . $controllerFileName, $out);

				$modelFileName = $plugin . 'AppModel.php';

				$out = "<?php\n\n";
				$out .= "class {$plugin}AppModel extends AppModel {\n\n";
				$out .= "}\n\n";
				$this->createFile($path . DS . 'Model' . DS . $modelFileName, $out);

				$this->hr();
				$this->out(__d('cake_console', '<success>Created:</success> %s in %s', $plugin, $path), 2);
				return true;
			case 'n':
				unset($this->args[0]);
				$this->execute();
				return false;
			case 'q':
				$this->out(__d('cake_console', '<error>Bake Aborted.</error>'));
				return false;

		}
	}

/**
 * find and change $this->path to the user selection
 *
 * @param array $pathOptions
 * @return string plugin path
 */
	public function findPath($pathOptions) {
		$valid = false;
		foreach ($pathOptions as $i => $path) {
			if (!is_dir($path)) {
				array_splice($pathOptions, $i, 1);
			}
		}
		$max = count($pathOptions);
		while (!$valid) {
			foreach ($pathOptions as $i => $option) {
				$this->out($i + 1 . '. ' . $option);
			}
			$prompt = __d('cake_console', 'Choose a plugin path from the paths above.');
			$choice = $this->in($prompt);
			if (intval($choice) > 0 && intval($choice) <= $max) {
				$valid = true;
			}
		}
		$this->path = $pathOptions[$choice - 1];
	}

/**
 * get the option parser for the plugin task
 *
 * @return void
 */
	public function getOptionParser() {
		$parser = parent::getOptionParser();
		return $parser->description(__d('cake_console',
			'Create the directory structure, AppModel and AppController classes for a new plugin. ' .
			'Can create plugins in any of your bootstrapped plugin paths.'
		))->addArgument('name', array(
			'help' => __d('cake_console', 'CamelCased name of the plugin to create.')
		));
	}

}
