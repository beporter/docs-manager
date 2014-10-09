<?php
// Usage docs. Include a sample cURL command to post a local zip file.


// $authToken = 'abc123'; // POSTed requests must be completely ignored if AUTH_TOKEN is not set to this.
// $basePath = dirname(__FILE__); // Subdirectories for each project will be created from this base.


// main()

// Error if $authToken not set.
// Error if $basePath not writeable.

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
