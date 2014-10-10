<?php
/**
 * doc-manager.php
 *
 * Acts as an upload destinatin point for ZIPed html documentation sets, such
 * as those produced by PHPUnit's Code Coverage, PHPDoc, or PHP CodeSniffer.
 *
 * ## Usage ##
 *
 * Will accept the POSTed ZIP file and extract it to a web-accessible
 * subfolder identified by the provided project name. Open this script in a
 * web brwoser with `?form` at the end of the URL for a full description and
 * an example <form>.
 *
 * An example client implementation is available in the
 * loadsys/CakePHP-Shell-Scripts repo in the script named `docs-post`. You
 * can also use Postman for testing: http://www.getpostman.com/
 *
 * ## Configuration ##
 *
 * Define these variables in the environment running the PHP script to
 * override the defaults below. At least `DOCMAN_AUTH_TOKEN` must be
 * defined and will be expected to authenticate every POST request under
 * an `authToken` key.
 *
 * `DOCMAN_BASE_PATH` defines the full or relative path from this script
 * where sub-folders should be created to store unpacked project docs. By
 * default, the folder this file lives in will be used. The folder should
 * be web-accessible and writeable by this script.
 *
 * Some of PHP's ini settings are critical for proper operation. The script
 * will return errors in its json result payload if any of these values are
 * incorrect or insufficient.
 */


/**
 * main()
 */
define('DOCMAN_DEFAULT_TOKEN', 'efb0b5884e868ec4b3800331d427a436e789f82c3ae71e7cba55053f935a48cf');
$runtime = new DocManager(
	checkEnv('DOCMAN_AUTH_TOKEN', DOCMAN_DEFAULT_TOKEN),
	checkEnv('DOCMAN_BASE_PATH', dirname(__FILE__))
);
$runtime->execute();




/**
 * DocManager class
 *
 * Acts as the main runtime for the script. Handles the creation and
 * coordination of all other related runtime objects.
 */
class DocManager {

	/**
	 * The authentication token value that all incoming POST requests must
	 * include.
	 *
	 * @var string
	 */
	protected $_token = null;

	/**
	 * Local filesystem path (full or relative to this script) where doc
	 * folders should be created. Should be web-accessible.
	 *
	 * @var string
	 */
	protected $_basePath = null;

	/**
	 * Stores an instance of a Request object, which contains details
	 * from $_POST, $_GET and $_FILES.
	 *
	 * @var Request
	 */
	protected $_request = null;

	/**
	 * Stores an instance of a Response object used to deliver a JSON
	 * payload back to the client.
	 *
	 * @var Response
	 */
	protected $_response = null;

	/**
	 * Initialize all necessary components for use.
	 *
	 * @param	string	$token		The authentication token value to expect with all POST requests.
	 * @param	string	$basePath	The local or full web-accessible filesystem path where uploads docs will be expanded.
	 */
	public function __construct($token, $basePath) {
		$this->_token = $token;
		$this->_basePath = $basePath;
		$this->_request = new Request($this->_token);
		$this->_response = new Response();
	}
	
	/**
	 * Execute the script's main logic. Responsible for checking ini settings,
	 * POST param validity, processing the request and delivering a response.
	 *
	 * @return	void
	 */
	public function execute() {
		$iniCheck = new IniCheck();
		//@TODO: Run $iniCheck->validate();

		if ($this->_request->isFormRequest()) {
			$this->displayForm();
		}

		if ($validationErrors = $this->_request->validationErrors()) {
			$this->_response
				->set(false, 'POST data is missing or invalid. Append ?form to the URL for an example HTML <form>.', $validationErrors)
				->deliver();
		}

		$vars = $this->_request->sanitizedVars();
		$destination = $this->_basePath . DIRECTORY_SEPARATOR . $vars['projectName'];

		$extractor = new Extractor($vars['file']);
		try {
			$extractor->extract($destination);
			//@TODO: Set .htaccess permissions!
		//     (These credentials must be shareable with clients and must be unique to prevent one client from
		//     accessing another client's docs.) (Error on failure.)
			$this->_response->set(true, 'Zip file extracted successfully.', $destination);
		} catch (Exception $e) {
			$this->_response->set(false, 'Zip file extraction failed.', $e->getMessage());	
		}

		$this->_response->deliver();
	}

	/**
	 * Renders a full HTML page to the browser and terminates this script.
	 *
	 * @return	void
	 */
	public function displayForm() {
		$html = <<<EOD
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Documentation Manager</title>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css">
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap-theme.min.css">
		<!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
		<!--[if lt IE 9]>
			<script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
			<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
		<![endif]-->
	</head>
	<body>
		<div class="container-fluid">
		<div class="row">
		<div class="col-md-12">
			<div class="jumbotron">
				<h1>Documentation Manager</h1>

				<p class="lead">Provides a mechanism to upload auto-generated documentation (PHPUnit code coverage reports, code sniff reports, PHPDoc manuals) to a web server via continuous integration such as Travis CI.</p>
			</div>

			<p>The script will respond to all POST requests with a JSON object containing the following attributes:</p>

			<dl class="dl-horizontal">
				<dt><code>status</code></dt>
				<dd>Boolean true or false indicating success or failure of the request, respectively.</dd>
				<dt><code>message</code></dt>
				<dd>A text string describing the result of the request.</dd>
				<dt><code>data</code></dt>
				<dd>Always an array, contains supplemental information relative to the given message or result.</dd>
			</dl>

			<div class="panel panel-info">
				<div class="panel-heading">
					<h3 class="panel-title">Example Form</h3>
				</div>
				<div class="panel-body">
					<p>This form allows for manual upload and demonstrates the necessary values to POST to this script.</p>

					<form role="form" action="?" method="post" enctype="multipart/form-data">
						<div class="form-group">
							<label for="authToken">authToken</label>
							<input type="text" id="authToken" name="authToken" placeholder="foo" class="form-control">
							<p class="help-block">Coded into the script to prevent abuse. Must be supplied with each POST request.</p>
						</div>
						<div class="form-group">
							<label for="projectName">projectName</label>
							<input type="text" id="projectName" name="projectName" placeholder="project-name" class="form-control">
							<p class="help-block">This will be used as a slug when creating the output directory on the web server, usually relative to the location of the <code>doc-manager.php</code> script.</p>
						</div>
						<div class="form-group">
							<label for="file">file</label>
							<input type="file" id="file" name="file" accept=".zip,application/zip" class="form-control">
							<p class="help-block">Filename must end in <code>.zip</code> and the provided mimeType must be <code>application/zip</code>.</p>
						</div>
						<button type="submit" class="btn btn-primary">Submit</button>
					</form>
				<div>
			<div>
		</div>
		</div>
		</div>
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js"></script>
	</body>
</html>

EOD;
		echo $html;
		exit;
	}
}


/**
 * Request class
 *
 * Provides a sanitized and validated interface to $_POST, $_GET and $_FILES.
 */
class Request {

	/**
	 * POSTed data captured from $_POST.
	 *
	 * @var array
	 */
	public $data = [];

	/**
	 * POSTed file uploads captured from $_FILES.
	 *
	 * @var array
	 */
	public $files = [];

	/**
	 * The authentication token value that all incoming POST requests must
	 * include.
	 *
	 * @var string
	 */
	protected $_authToken = null;

	/**
	 * Initialize all necessary components for use.
	 *
	 * @param	string	$token		The authentication token value to expect with all POST requests.
	 */
	public function __construct($authToken) {
		$this->data = $_POST;
		$this->files = $_FILES;
		$this->_authToken = $authToken;
	}

	/**
	 * Returns true when $_GET['form'] is set.
	 *
	 * @return	bool				True when $_GET['form'] is set, false otherwise.
	 */
	public function isFormRequest() {
		return isset($_GET['form']);
	}

	/**
	 * Verifies the presence and value of all expected POST variables.
	 * Returns an array of error strings when problems are encountered,
	 * and false if there are zero validation errors to report.
	 *
	 * @return	false|array				False when zero errors, array of string error messages when validation fails in any way.
	 */
	public function validationErrors() {
		$errors = false;

		if (empty($this->data['authToken'])) {
			$errors[] = '`authToken` missing from POSTed vars.';
		}
		if (isset($this->data['authToken']) && $this->data['authToken'] == DOCMAN_DEFAULT_TOKEN) {
			$errors[] = '`authToken` has not been properly configured.';
		}
		if (isset($this->data['authToken']) && $this->data['authToken'] != $this->_authToken) {
			$errors[] = '`authToken` value is incorrect.';
		}
		if (empty($this->data['projectName'])) {
			$errors[] = '`projectName` missing from POSTed vars.';
		}
		if (empty($this->files['file'])) {
			$errors[] = '`file` upload missing from POSTed data.';
		}
		if (isset($this->files['file']['error']) && $this->files['file']['error'] > 0) {
			$errors[] = sprintf('`file` upload failed with PHP error code %s.', $this->files['file']['error']);
		}
		if (isset($this->files['file']['name']) && pathinfo($this->files['file']['name'], PATHINFO_EXTENSION) !== 'zip') {
			$errors[] = '`file` upload does not end in .zip.';
		}
		if (isset($this->files['file']['type']) && $this->files['file']['type'] !== 'application/zip') {
			$errors[] = '`file` upload mime time is not application/zip.';
		}

		return $errors;
	}

	/**
	 * Convenience getter method to extract the sanitized parts of the
	 * request we need to deal with further. (The temp uploaded file and
	 * the project name to use when extracting it.)
	 *
	 * @return	array				Always contains two keys: [file], which contains standard PHP uploaded file info, and [projectName] which is a filesystem-santized string safe for creating a folder to contain the extracted docs.
	 */
	public function sanitizedVars() {
		$file = $this->files['file'];
		$projectName = $this->_sanitizeProjectName($this->data['projectName']);
		return compact('projectName', 'file');
	}

	/**
	 * Strips disallowed characters from the provided string and returns
	 * the sanitized result.
	 *
	 * @param	string	$p			A string to sanitize.
	 * @return	array				The lowercase, alnum-only version of the string.
	 */
	protected function _sanitizeProjectName($p) {
		return strtolower(preg_replace('/[^a-z0-9_-]/i', '', $p));
	}
}



/**
 * Response class
 *
 * Provides an interface for delivering consistent JSON payloads back to
 * the browser.
 */
class Response {

	/**
	 * Stores the exit status of the response. True for "success" and
	 * false for "failure".
	 *
	 * @var bool
	 */
	protected $_status = true;

	/**
	 * Stores a string description of the result.
	 *
	 * @var string
	 */
	protected $_message = '';

	/**
	 * Stores additional payload data for the result.
	 *
	 * @var array
	 */
	protected $_data = [];

	/**
	 * Initialize all necessary components for use.
	 *
	 * @param	string	$status		Optional status state to set during object creation.
	 * @param	string	$message	Optional message to set during object creation.
	 * @param	array	$data		Optional data payload to set during object creation.
	 */
	public function __construct($status = null, $message = null, $data = null) {
		$this->set($status, $message, $data);
	}

	/**
	 * Set response values.
	 *
	 * @param	string	$status		Optional status state to set. Ignored when null.
	 * @param	string	$message	Optional message to set. Ignored when null.
	 * @param	array	$data		Optional data payload to set. Ignored when null.
	 */
	public function set($status = null, $message = null, $data = null) {
		if (!is_null($status)) {
			$this->_status = (bool)$status;
		}
		if (!is_null($message)) {
			$this->_message = (string)$message;
		}
		if (!is_null($data)) {
			$this->_data = (array)$data;
		}
		return $this;
	}

	/**
	 * Sets a json content type header, prints the json resposne object and
	 * terminates script execution.
	 *
	 * @return	void
	 */
	public function deliver() {
		$json = [
			'status' => $this->_status,
			'message' => $this->_message,
			'data' => $this->_data,
		];
		header('Content-Type: application/json');
		echo json_encode($json, JSON_PRETTY_PRINT);
		exit;
	}
}

/**
 * Extractor class
 *
 * Wrapper around the ZipArchive class. Handles creating or recreating
 * the destination folder and extracting the doc bundle to it.
 */
class Extractor {

	/**
	 * Stores the details for an uploaded file. Format of the array must
	 * match the format used in $_FILES.
	 *
	 * @ref http://php.net/manual/en/reserved.variables.files.php
	 * @ref http://php.net/manual/en/features.file-upload.errors.php
	 * @var array
	 */
	protected $_file = null;

	/**
	 * Initialize all necessary components for use.
	 *
	 * @param	array	$uploadedFile	An array of properties for an uploaded file. Must contain at least a [tmp_name] key populated with a local filesystem path to the uploaded file.
	 */
	public function __construct($uploadedFile) {
		$this->_file = $uploadedFile;
	}

	/**
	 * Extract the ZIP file into the destination path, creating or
	 * recreating it if necessary. The tmp file will be deleted after
	 * successful extraction.
	 *
	 * @param	string	$path		A local filesystem path to extract into. **WARNING** Will be created if not present, and deleted and recreated if it is.
	 * @return	true				True when the ZIP was fully extracted successfully.
	 * @throws	Exception			If the destination folder isn't writeable, can't be created or created, or the ZIP can't be opened or extracted. The Exception will contain an error message string.
	 */
	public function extract($path) {
		if (!is_dir($path)) {
			if(!mkdir($path, 0777, true)) {
				throw new Exception(sprintf('Requested destination path could not be created: %s', $path));
			}
		} else {
			if (!rm_r($path)) {
				throw new Exception(sprintf('Requested destination path could not be purged before recreation: %s', $path));
			}
			if(!mkdir($path, 0777, true)) {
				throw new Exception(sprintf('Requested destination path could not be recreated: %s', $path));
			}
		}
		if (!is_writable($path)) {
			throw new Exception(sprintf('Requested destination path is not writeable: %s', $path));
		}

		$archive = new ZipArchive();
		if (($result = $archive->open($this->_file['tmp_name'])) !== true) {
			throw new Exception(sprintf('Zip file could not be opened. Error code: %s', $result));
		}
		if (!$archive->extractTo($path)) {
			throw new Exception(sprintf('Zip file could not be extracted to path: %s', $path));
		}
		$archive->close();
		unlink($this->_file['tmp_name']);

		return true;
	}
}



class PhpIniException extends Exception {
	//protected $_messageTemplate = 'php.ini %s is too small! (Current: %s, Minimum required: %s)';
}

class IniCheck {
	protected $ini_required_mins = array(
			// Can be changed at runtime.
			'memory_limit' => '600M',
			'max_execution_time' => 1200,  // 20 minutes

			// These MUST be changed in the php.ini or .htaccess file!
			'post_max_size' => '600M',
			'upload_max_filesize' => '200M',
			'max_input_time' => 1200,  // 20 minutes
		);


	public function validate() {

// 	$data = compact('_POST', '_FILES');
// 	$data[] = ini_get('upload_tmp_dir');
// 	$data[] = is_writeable(ini_get('upload_tmp_dir'));

		$uploadTmpDir = ini_get('upload_tmp_dir');
		if( empty($uploadTmpDir) ) {
			throw new PhpIniException( array('ini' => 'upload_tmp_dir', 'cur' => '(blank)', 'min' => '(must be set to a valid dir)') );
		}

		foreach($this->ini_required_mins as $ini => $min)
		{
			$cur = ini_get($ini);
			if( self::iniLessThan($cur, $min) ) {
				throw new PhpIniException( array('ini' => $ini, 'cur' => $cur, 'min' => $min) );
			}
		}
	}

	//-----------------------------------------------------------------------
	public static function iniLessThan($b1, $b2)
	// Takes two php.ini-style values for memory etc (i.e: "200M") and compares them. Returns true if $v1 is less than $v2. For example: '200K' is less than '10M'. Borrows some code from https://github.com/milesj/cake-uploader
	{
		return self::bytes($b1, 'size') < self::bytes($b2, 'size');
	}

	public static function bytes($size, $return = null) {
		if (!is_numeric($size)) {
			$byte = preg_replace('/[^0-9]/i', '', $size);
			$last = mb_strtoupper(preg_replace('/[^a-zA-Z]/i', '', $size));

			if ($return == 'byte') {
				return $last;
			}

			switch ($last) {
				case 'T': case 'TB': $byte *= 1024;
				case 'G': case 'GB': $byte *= 1024;
				case 'M': case 'MB': $byte *= 1024;
				case 'K': case 'KB': $byte *= 1024;
			}

			$size = $byte;
		}

		if ($return == 'size') {
			return $size;
		}

		$sizes = array('YB', 'ZB', 'EB', 'PB', 'TB', 'GB', 'MB', 'KB', 'B');
		$total = count($sizes);

		while ($total-- && $size > 1024) {
			$size /= 1024;
		}

		return round($size, 0) . ' ' . $sizes[$total];
	}

	public function action()
	{
// 		$this->set('title_for_layout', 'php.ini Configuration Check');
// 		$this->set('ini_required_mins', $this->ini_required_mins);
	}

/*

<div class="videos index">
	<h2><?php echo __($title_for_layout);?></h2>
	<p>Because this app handles large file uploads, the following php.ini settings <strong>must</strong> meet certain minimum values. Below is a report of the current and required-minimum values that need to be modified for this app to function properly. Even these values may prove to be too low in the future.</p>
	<table>
	<tr>
		<th>php.ini Directive</th>
		<th>Current Value</th>
		<th>Minimum Required</th>
	</tr>

<?php
$htaccess = '';
foreach($ini_required_mins as $ini => $min): 
	$cur = ini_get($ini);
	$htaccess .= "php_value {$ini} {$min}\n";
?>
	<tr>
		<td><?php echo $ini; ?>&nbsp;</td>
		<td><?php echo $cur; ?>&nbsp;</td>
		<td><?php echo $min; ?>&nbsp;</td>
	</tr>
<?php endforeach; ?>
	</table>

<p>The following directives <strong>must</strong> be present in the <code>.htaccess</code> in the web root directory of this application:

<pre>
<?php echo $htaccess; ?>
</pre>

<br/>
<p>These directives are based on reasonable usage, but may need to be modified over time. Please contact <a href="http://helpinghandpc.com">Helping Hand PC</a> if you start seeing upload errors or timeouts.</p>

</div>
<div class="actions">
	<h3><?php echo __('Actions'); ?></h3>
	<ul>
		<li><?php echo $this->Html->link(__('List Current Videos'), array('controller' => 'videos', 'action' => 'index')); ?> </li>
	</ul>
</div>

*/

}


/**
 * Fetch an environment variable by name, or use a default.
 *
 * Checks for an environment variables identified by $key and returns
 * the value if non-empty, otherwise returns $default.
 *
 * @param	string	$key		A string representing the environment variable name to look up.
 * @param	string	$default	A default value to use if the env var is empty.
 * @return	string				The defined value of the env var, or the default string.
 */
function checkEnv($key, $default) {
	$val = getenv($key);
	$val = (!empty($val) ? $val : $default);
	return $val;
}

/**
 * Recursively delete a directory and all of its contents.
 *
 * - e.g. the equivalent of `rm -r` on the command-line. Consistent with
 * `rmdir()` and `unlink()`, an E_WARNING level error will be generated
 * on failure.
 *
 * @ref	https://gist.github.com/mindplay-dk/a4aad91f5a4f1283a5e2
 * @param	string	$dir	Absolute path to directory to delete.
 * @return	bool			True on success; false on failure.
 */
function rm_r($dir){
    /** @var SplFileInfo[] $files */
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        if ($fileinfo->isDir()) {
            if (false === rmdir($fileinfo->getRealPath())) {
                return false;
            }
        } else {
            if (false === unlink($fileinfo->getRealPath())) {
                return false;
            }
        }
    }

    return rmdir($dir);
}

