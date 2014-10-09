<?php

// $authToken = 'abc123'; // POSTed requests must be completely ignored if AUTH_TOKEN is not set to this.
// $basePath = dirname(__FILE__); // Subdirectories for each project will be created from this base.

// main()

// If POST and AUTH_TOKEN is valid and PROJECT_NAME is present.
//  Slugify PROJECT_NAME if necessary to make it filesystem path safe.
//  Create a folder for $basePath + $projectSlug if it doesn't already exist.
//  Save POSTed ZIP to tmp.
//  Extract ZIP to destination path.
//  Write an .htacess file for Basic Auth using $projectSlug for the username and (something?) for password. (These credentials must be shareable with clients and must be unique to prevent one client from accessing another client's docs.)
