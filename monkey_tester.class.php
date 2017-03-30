<?php

@session_start();

class MonkeyTester implements RocketSled\Runnable {

    const FIXTURES_PATH = "./smoke_tests/fixtures/";

    private $dbconfig_path = ""; // Path of dbconfig file
    private $dbconfig = array();
    private $action = "";
    private $fixtures = "";
    private $fixture_path = "";
    private $forbiden_hostname = "";
    private $current_hostname = "";

    public function run() {

	$this->action = Args::get('action', $_GET);
	$this->fixtures = Args::get('fixtures', $_GET);
	$this->dbconfig_path = Args::get('dbconfig', $_GET);
	$this->fixture_path = Args::get('fixture_path', $_GET);
	$this->forbiden_hostname = Args::get('forbiden_hostname', $_GET);
	$this->current_hostname = php_uname('n');

	if (!isset($this->action)) {
	    echo "No action is specified." . PHP_EOL;
	    echo "Usage: php index.php MonkeyTester action=/action/to/be/taken" . PHP_EOL;
	    exit(1);
	}
	else if ($this->action == "LOAD_FIXTURE" && !($this->dbconfig_path)) {
	    echo "You need to include 'dbconfig' in the command line arguments." . PHP_EOL;
	    echo "The file should have the following format: " . PHP_EOL;
	    echo "<?php" . PHP_EOL;
	    echo "return array('db_host' => 'localhost'," . PHP_EOL;
	    echo "             'db_user' => 'root'," . PHP_EOL;
	    echo "             'db_pass' => 'root'," . PHP_EOL;
	    echo "             'db_name' => 'killerapp'," . PHP_EOL;
	    echo "             'db_port' => 3309" . PHP_EOL;
	    echo "?>" . PHP_EOL;
	    echo PHP_EOL;
	    echo "Usage: php index.php MonkeyTester dbconfig=/path/to/dbconfig.php" . PHP_EOL;
	    exit(1);
	}
	else if ($this->action == "LOAD_FIXTURE" && !file_exists($this->dbconfig_path)) {
	    echo "dbconfig not found at path {$this->dbconfig_path}." . PHP_EOL;
	    exit(1);
	}
	else if ($this->action == "LOAD_FIXTURE" && !isset($this->fixtures)) {
	    echo "No fixture is specified." . PHP_EOL;
	    echo "Usage: php index.php MonkeyTester fixture=/fixture(s)/to/be/loaded" . PHP_EOL;
	    exit(1);
	}

	if ($this->action == "CREATE_ENVIROMENT") {
	    if (isset($this->forbiden_hostname) && $this->forbiden_hostname == $this->current_hostname) {
		echo "Enviroment Not Created, Cannot run tests on this host";
	    }
	    else {
		@session_unset();
		$_SESSION["SMOKE_TESTING_ENVIRONMENT"] = TRUE;
		echo "Enviroment Created";
	    }
	}
	elseif ($this->action == "LOAD_FIXTURE") {

	    $this->dbconfig = include($this->dbconfig_path);
	    $this->fixtures = explode(",", $this->fixtures);
	    $this->fixture_path = strlen($this->fixture_path) ? $this->fixture_path : self::FIXTURES_PATH;

	    if ($this->fixtureExists($this->fixtures)) {
		$monkeyFixture = \Murphy\Fixture::load($this->dbconfig["db_name"], $this->fixture_path . $this->fixtures[0]);
		if (sizeof($this->fixtures) > 1) {
		    for ($i = 1; $i < sizeof($this->fixtures); $i++) {
			$monkeyFixture->also($this->dbconfig["db_name"], $this->fixture_path . $this->fixtures[$i]);
		    }
		}
		$monkeyFixture->execute(function() {
		    $this->link = mysqli_connect($this->dbconfig["db_host"], $this->dbconfig["db_user"], $this->dbconfig["db_pass"]);
		    $this->link->select_db($this->dbconfig["db_name"]);
		});
	    }
	}
	elseif ($this->action == "DESTROY_ENVIROMENT") {
	    if (isset($_SESSION["SMOKE_TESTING_ENVIRONMENT"]))
		unset($_SESSION["SMOKE_TESTING_ENVIRONMENT"]);
	}
    }

    function fixtureExists($fixtures) {
	foreach ($fixtures as $fixture) {
	    if (!file_exists($this->fixture_path . $fixture)) {
		echo "fixture '{$fixture}' not found." . PHP_EOL;
		exit(1);
	    }
	}
	return TRUE;
    }

    static function isTestingEnviromentSet() {
	if (isset($_SESSION["SMOKE_TESTING_ENVIRONMENT"]) && $_SESSION["SMOKE_TESTING_ENVIRONMENT"] == TRUE) {
	    return TRUE;
	}
	return FALSE;
    }

}
