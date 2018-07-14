<?php

/**
 * Recurses each directory and runs PHP's lint function against each file to test for parse errors.
 *
 * @since 4.0.0
 *
 * @param string $dir The directory you would like to start from.
 * @return array The files that did not pass the test.
 */
function bp_lint( $dir ) {
	static $failed = array();

	$excluded_dirs  = array();
	$excluded_files = array();

	foreach ( new RecursiveDirectoryIterator( $dir ) as $path => $objSplFileInfo ) {
		// Recurse if directory.
		if ( $objSplFileInfo->isDir() ) {
			if ( stristr( $objSplFileInfo->getFileName(), '.svn' ) !== false ) {
				continue;
			}

			if ( '.' === $objSplFileInfo->getFileName() || '..' === $objSplFileInfo->getFileName() ) {
				continue;
			}

			$relativePath = bp_lint_get_relative_path( $objSplFileInfo->getRealPath() );

			if ( in_array( $relativePath, $excluded_dirs, true ) ) {
				continue;
			}

			bp_lint( $objSplFileInfo->getPathName() );

			continue;
		}

		// are there any non-dirs that aren't files?
		if ( ! $objSplFileInfo->isFile() ) {
			throw new UnexpectedValueException( 'Not a dir and not a file?' );
		}

		// skip non-php files
		if ( preg_match( '#\.php$#', $objSplFileInfo->getFileName() ) !== 1 ) {
			continue;
		}

		// Blacklist.
		$relativePath = bp_lint_get_relative_path( $objSplFileInfo );
		if ( in_array( $relativePath, $excluded_files, true ) ) {
			continue;
		}

		// Perform the lint check.
		$result = exec( 'php -l ' . escapeshellarg( $objSplFileInfo ) );
		if ( preg_match( '#^No syntax errors detected in#', $result ) !== 1 ) {
			$failed[ $objSplFileInfo->getPathName() ] = $result;
		}
	}

	echo '.';
	return $failed;
}

/**
 * Gets a relative path.
 *
 * @since 4.0.0
 *
 * @param string $path Full path.
 * @return string
 */
function bp_lint_get_relative_path( $path ) {
	return str_replace( realpath( __DIR__ . '/../' ), '', $path );
}

echo 'Linting...';
$failed = bp_lint( realpath( $argv[1] ) );
echo "\n";
if ( empty( $failed ) ) {
	echo 'All checks passed.' . "\n";
	exit( 0 );
} else {
	echo 'Errors found in the following files:' . "\n";
	print_r( $failed );
	echo "\n";
	exit( 1 );
}
