<?php

//Declaring namespace
namespace LaswitchTech\phpUpdater;

//Import phpConfigurator class into the global namespace
use LaswitchTech\phpConfigurator\phpConfigurator;

//Import phpLogger class into the global namespace
use LaswitchTech\phpLogger\phpLogger;

// Importing Dependencies
use DateTime;
use Exception;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class phpUpdater {

    // Configurator
    private $Configurator = null;

    // Logger
    private $Logger = null;

    // Database
    private $Database = null;

    // Properties
    private $URL = null;
    private $Token = null;
    private $Owner = null;
    private $Repository = null;
    private $Version = null;
    private $ID = null;
    private $Latest = null;
    private $Releases = null;

    /**
     * Create a new phpUpdater instance.
     *
     * @param  string|array|null  $logFile
     * @return void
     * @throws Exception
     */
    public function __construct(){

        // Initialize Configurator
        $this->Configurator = new phpConfigurator('updater');

        // Initialize Configurator
        $this->Logger = new phpLogger();

        // Retrieve Updater Settings
        $this->Token = $this->Configurator->get('updater', 'token') ?: $this->Token;
        $this->Owner = $this->Configurator->get('updater', 'owner') ?: $this->Owner;
        $this->Repository = $this->Configurator->get('updater', 'repository') ?: $this->Repository;
        $this->ID = $this->Configurator->get('updater', 'id') ?: $this->ID;

        // // Retrieve Latest Release
        // $this->fetch();

        // Check if phpDatabase is installed
        if (class_exists('LaswitchTech\phpDB\Database')) {
            $this->Database = new \LaswitchTech\phpDB\Database();
        }
    }

    /**
     * Configure Library.
     *
     * @param  string  $option
     * @param  bool|int  $value
     * @return void
     * @throws Exception
     */
    public function config($option, $value){
        if(is_string($option)){
            switch($option){
                case"token":
                case"owner":
                case"repository":
                case"version":
                    if(is_string($value)){
                        // Save to Configurator
                        $this->Configurator->set('updater',$option, $value);
                    } else{
                        throw new Exception("2nd argument must be a string.");
                    }
                    break;
                case"id":
                    if(is_int($value)){
                        // Save to Configurator
                        $this->Configurator->set('updater',$option, $value);
                    } else{
                        throw new Exception("2nd argument must be an integer.");
                    }
                    break;
                default:
                    throw new Exception("unable to configure $option.");
                    break;
            }
        } else{
            throw new Exception("1st argument must be as string.");
        }

        return $this;
    }

    /**
     * Retrieve the latest release.
     *
     * @return string
     * @throws Exception
     */
    private function fetch(){
        try{

            // Check if Token is set
            if($this->Token == null){
                throw new Exception("Token is not set.");
            }

            // Check if Owner is set
            if($this->Owner == null){
                throw new Exception("Owner is not set.");
            }

            // Check if Repository is set
            if($this->Repository == null){
                throw new Exception("Repository is not set.");
            }

            // Build URL
            $this->URL = "https://api.github.com/repos/" . $this->Owner . "/" . $this->Repository;

            // Check if URL is set
            if($this->URL == null){
                throw new Exception("URL is not set.");
            }

            $this->Logger->debug($this->URL);

            // Initialize cURL
            $curl = curl_init($this->URL . "/releases/latest");
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->Token,
                'User-Agent: PHP'
            ]);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            // Execute cURL
            $response = curl_exec($curl);

            // Close cURL
            curl_close($curl);

            if ($response) {

                // Save to Object
                $this->Latest = json_decode($response, true);

                // Initialize cURL
                $curl = curl_init($this->URL . "/releases");
                curl_setopt($curl, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $this->Token,
                    'User-Agent: PHP'
                ]);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    
                // Execute cURL
                $response = curl_exec($curl);
    
                // Close cURL
                curl_close($curl);

                if ($response) {

                    // Save to Object
                    $this->Releases = json_decode($response, true);
                } else {
    
                    // Handle any errors that occurred during the request
                    throw new Exception("Failed to retrieve the releases.");
                }
            } else {

                // Handle any errors that occurred during the request
                throw new Exception("Failed to retrieve the latest release.");
            }
        } catch (Exception $e) {
            $this->Logger->error('Error: '.$e->getMessage());
        }
    }

	/**
	 * Helper function to identify the lastest file in a path.
	 *
	 * @param  string  $value
	 * @return boolean
	 */
	private function getLastCreatedFile($path, $extension = 'zip') {
		// Initialize variables to store the name and creation time of the latest file
		$latestFilePath = '';
		$latestFileTime = 0;
	
		// Create a directory handle
		$directoryHandle = opendir($path);
	
		// If successful in opening the directory
		if ($directoryHandle) {
			// Loop over all the files in the directory
			while (false !== ($entry = readdir($directoryHandle))) {
				// Skip non-files (like ".", "..", or subdirectories)
				if (is_file($path . '/' . $entry) && filectime($path . '/' . $entry) > $latestFileTime) {

					// Check if the file extension matches the provided extension
					$fileExtension = pathinfo($entry, PATHINFO_EXTENSION);
					if ($fileExtension === $extension) {
						$latestFilePath = $path . '/' . $entry;
						$latestFileTime = filectime($latestFilePath);
					}
				}
			}
			// Close the directory handle
			closedir($directoryHandle);
		}
	
		return $latestFilePath;
	}

    /**
     * Remove Directory Recursively.
     *
     * @return string
     * @throws Exception
     */
    private function rmdir($directoryPath) {
        try{
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directoryPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
    
            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                $todo($fileinfo->getRealPath());
            }
    
            rmdir($directoryPath);
        } catch (Exception $e) {
            $this->Logger->error('Error: '.$e->getMessage());
        }
    }

    /**
     * Check for Updates.
     *
     * @return bool
     * @throws Exception
     */
    public function check($fetch = false){

        // Check if Latest is set
        if($this->Latest == null || $fetch){
            $this->fetch();
        }

        // Check if Latest is set and if the latest release is newer than the current version
        return $this->Latest && $this->Latest['id'] > $this->ID;
    }

    /**
     * Backup the current version.
     *
     * @return string
     * @throws Exception
     */
    public function backup($filename = null, $exclude = ['tmp', 'backup']){
        try{

            // Set Path to Backup Folder
            $path = $this->Configurator->root() . "/backup";

			// If no filename was provided, generate a filename
			if (is_null($filename)) {
				$filename = time() . ".zip";
			} else {
				$filename = $filename . ".zip";
			}

			$this->Logger->info("Creating backup to: " . $path . "/" . $filename);

            // Check if phpDatabase is installed
            if($this->Database){

                // Backup Database
                $database = $this->Database->backup();

                $this->Logger->info("Creating database backup to: " . $database);
            }

			// Create the directory recursively
			if(!is_dir(dirname($path . "/" . $filename))){
				mkdir(dirname($path . "/" . $filename), 0777, true);
			}
    
            // Create a new zip archive
            $zip = new ZipArchive();
            $zip->open($path . '/' . $filename, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            // Iterate over all files and directories in the root folder
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->Configurator->root(), RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iterator as $file) {
                $filePath = $file->getPathname();
                $relativePath = substr($filePath, strlen($this->Configurator->root()) + 1);

                $this->Logger->debug("filePath: " . $filePath);
    
                // Skip excluded folders/files
                if (in_array($file->getBasename(), $exclude) || in_array($relativePath, $exclude)) {

                    $this->Logger->debug("Skipping file: " . $filePath);
                    continue;
                }

                // Skip excluded folders
                foreach($exclude as $excludedDir) {
                    if (strpos($relativePath, $excludedDir) === 0) {

                        $this->Logger->debug("Skipping file: " . $filePath);
                        continue 2;
                    }
                }
        
                // Add file or directory to the zip archive
                if ($file->isFile()) {
                    $zip->addFile($filePath, $relativePath);
                } elseif ($file->isDir()) {
                    $zip->addEmptyDir($relativePath);
                }
            }
    
            // Close the zip archive
            $zip->close();
            
            // Return the path to the backup file
            return $path . '/' . $filename;
        } catch (Exception $e) {
            $this->Logger->error('Error: '.$e->getMessage());
        }
    }

    /**
     * Restore from a backup.
     *
     * @return string
     * @throws Exception
     */
    public function restore($filename = null, $exclude = ['tmp', 'backup']){
        try{

			if($filename){
				// Convert file to absolute path
				$filename = $this->Configurator->root() . $filename;
			} else {
				// Get the last backup file
				$filename = $this->getLastCreatedFile($this->Configurator->root() . "/backup/");
			}

			if(!file_exists($filename)){
				throw new Exception("File does not exist: " . $filename);
			}

			$this->Logger->info("Clearing root directory.");

            // Clear the root directory
            // Iterate over all files and directories in the root folder
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->Configurator->root(), RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $file) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($this->Configurator->root()) + 1);

                $this->Logger->debug("filePath: " . $filePath);
    
                // Skip excluded folders/files
                if (in_array($file->getBasename(), $exclude) || in_array($relativePath, $exclude)) {

                    $this->Logger->debug("Skipping file: " . $filePath);
                    continue;
                }

                // Skip excluded folders
                foreach($exclude as $excludedDir) {
                    if (strpos($relativePath, $excludedDir) === 0) {

                        $this->Logger->debug("Skipping file: " . $filePath);
                        continue 2;
                    }
                }

                // Delete file or directory
                $todo = ($file->isDir() ? [$this, 'rmdir'] : 'unlink');
                $todo($filePath);
            }

			$this->Logger->info("Restoring backup from file: " . $filename);
                
            // Open the zip archive
            $zip = new ZipArchive();
            if ($zip->open($filename) !== TRUE) {
                throw new Exception("Cannot open the ZIP file: " . $fifilenamele);
            }

            // Extract the zip archive
            $zip->extractTo($this->Configurator->root());
    
            // Close the zip archive
            $zip->close();

            // Check if phpDatabase is installed
            if($this->Database){

                $this->Logger->info("Restoring database");

                // Restore Database
                $database = $this->Database->restore();
            }
        } catch (Exception $e) {
            $this->Logger->error('Error: '.$e->getMessage());
        }
    }

    /**
     * Update the current version.
     *
     * @return void
     * @throws Exception
     */
    public function update(){
        try{

            if(!$this->check()){
                throw new Exception("No update available.");
            }

            $this->Logger->info("Updating to version: " . $this->Latest['id']);
            $this->Logger->debug($this->Latest);
        } catch (Exception $e) {
            $this->Logger->error('Error: '.$e->getMessage());
        }
    }

    /**
     * Rollback to the last backup.
     *
     * @return void
     * @throws Exception
     */
    public function rollback(){
        try{} catch (Exception $e) {
            $this->Logger->error('Error: '.$e->getMessage());
        }
    }
}
