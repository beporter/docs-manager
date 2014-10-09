<?php
// Usage docs. Include a sample cURL command to post a local zip file.
// Example cURL command:
// curl -FauthToken=foobar -FprojectName=test -Ffile=@docs-folder.zip http://localhost/docs-manager.php
//@TODO: test and tweak the above.
// You can also use Postman for testing: http://www.getpostman.com/


// POSTed requests must be completely ignored if AUTH_TOKEN is not set to this.
//define('AUTH_TOKEN', hash('sha256', 'foobar')); //@TODO: re-enable
define('AUTH_TOKEN', 'foobar');
// Subdirectories for each project will be created from this base.
//define('BASE_PATH', dirname(__FILE__)); //@TODO: re-enable
define('BASE_PATH', '/Library/WebServer/Documents/docs-manager');

// main()

$iniCheck = new IniCheck();
$request = new Request(AUTH_TOKEN);
$response = new Response();


//@TODO: Run $iniCheck->validate();

if ($validationErrors = $request->validationErrors()) {
	$response
		->set(false, 'Post data is invalid.', $validationErrors)
		->deliver();
}

$vars = $request->sanitizedVars();
$destination = BASE_PATH . DIRECTORY_SEPARATOR . $vars['projectName'];

$extractor = new Extractor($vars['file']);
try {
	$extractor->extract($destination);
	//@TODO: Set .htaccess permissions!
	$response->set(true, 'Zip file extracted successfully.', $destination);
} catch (Exception $e) {
	$response->set(false, 'Zip file extraction failed.', $e->getMessage());	
}


// If POST and AUTH_TOKEN is valid and PROJECT_NAME is present.
//  Slugify PROJECT_NAME if necessary to make it filesystem path safe.
//  Create a folder for $basePath + $projectSlug if it doesn't already exist. (Error on failure.)
//  Save POSTed ZIP to tmp. (Error on failure.)
//  Extract ZIP to destination path. (Error on failure.)
//  Write an .htaccess file for Basic Auth using $projectSlug for the username and (something?) for password.
//     (These credentials must be shareable with clients and must be unique to prevent one client from
//     accessing another client's docs.) (Error on failure.)
// Else
//  No output? Help output (in json)?

// Return json to caller with status.
$response->deliver();



class Request {
	public $data = [];
	public $files = [];
	protected $_authToken = null;

	public function __construct($authToken) {
		$this->data = $_POST;
		$this->files = $_FILES;
		$this->_authToken = $authToken;
	}

	public function validationErrors() {
		$errors = false;

		if (empty($this->data['authToken'])) {
			$errors[] = '`authToken` missing from POSTed vars.';
		}
		if ($this->data['authToken'] != $this->_authToken) {
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
		if (pathinfo($this->files['file']['name'], PATHINFO_EXTENSION) !== 'zip') {
			$errors[] = '`file` upload does not end in .zip.';
		}
		if ($this->files['file']['type'] !== 'application/zip') {
			$errors[] = '`file` upload mime time is not application/zip.';
		}

		return $errors;
	}

	// Returns sanitized values for the recognized POST vars as an associative array.
	public function sanitizedVars() {
		$file = $this->files['file'];
		$projectName = $this->sanitizeProjectName($this->data['projectName']);
		return compact('projectName', 'file');
	}

	protected function sanitizeProjectName($p) {
		return strtolower(preg_replace('/[^a-z0-9_-]/i', '', $p));
	}
}


class Response {
	protected $_status = true;
	protected $_message = '';
	protected $_data = [];

	public function __construct($status = null, $message = null, $data = null) {
		$this->set($status, $message, $data);
	}

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

class Extractor {
	protected $_file = null;

	public function __construct($uploadedFile) {
		$this->_file = $uploadedFile;
	}

	public function extract($path) {
		if (!is_dir($path)) {
			if(!mkdir($path, 0777, true)) {
				throw new Exception(sprintf('Requested destination path could not be created: %s', $path));
			}
		} elseif (!rm_r($path)) {
			if(!mkdir($path, 0777, true)) {
				throw new Exception(sprintf('Requested destination path could not be recreated: %s', $path));
			} else {
				throw new Exception(sprintf('Requested destination path could not be purged before recreation: %s', $path));
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
}


/**
 * Recursively delete a directory and all of it's contents - e.g.the equivalent of `rm -r` on the command-line.
 * Consistent with `rmdir()` and `unlink()`, an E_WARNING level error will be generated on failure.
 *
 * @ref https://gist.github.com/mindplay-dk/a4aad91f5a4f1283a5e2
 * @param string $dir absolute path to directory to delete
 * @return bool true on success; false on failure
 */
function rm_r($dir)
{
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