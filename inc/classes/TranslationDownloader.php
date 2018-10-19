<?php

/**
 * Class WPIDG_Translation_Downloader
 */
class WPIDG_Translation_Downloader {

	private $slug;
	private $version;
	private $type;

	public function __construct( $slug, $version, $type = 'plugin' ) {
		$this->slug    = $slug;
		$this->version = $version;
		$this->type    = $type;
	}

	/**
	 * Download translation files from woocommerce repo.
	 *
	 * @global \WP_Filesystem_Base $wp_filesystem
	 *
	 * @param string $locale locale
	 * @param string $name
	 * @param bool $overwrite
	 *
	 * @return bool true when the translation is downloaded successfully
	 *
	 * @throws \RuntimeException on errors
	 */
	public function download( $locale, $name = '', $overwrite = false ) {
		// Bail if already downloaded.
		if ( $this->is_downloaded( $locale ) ) {
			if ( ! $overwrite ) {
				return true;
			} else {
				unlink( $this->get_local_path( $locale ) );
			}
		}
		// Bail if no translation available
		if ( ! $this->is_available( $locale ) ) {
			throw new \RuntimeException( 'Translation for ' . $this->slug . ' ' . $this->type . ' can not be found.' );
		}

		// Init download request.
		$response = wp_remote_get( sprintf( '%s/%s.zip', $this->get_repository_url(), $locale ), array(
			'sslverify' => false,
			'timeout'   => 200
		) );

		$download_error = 'Unable to download translation for ' . $this->slug . ' ' . $this->type . '.';

		if ( ! is_wp_error( $response ) && ( $response['response']['code'] >= 200 && $response['response']['code'] < 300 ) ) {

			/**
			 * Initialize the WP filesystem, no more using 'file-put-contents' function
			 * @var WP_Filesystem_Base $wp_filesystem
			 */
			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
				require_once ABSPATH . '/wp-admin/includes/file.php';
				if ( false === ( $creds = request_filesystem_credentials( '', '', false, false, null ) ) ) {
					throw new \RuntimeException( $download_error );
				}
				if ( ! WP_Filesystem( $creds ) ) {
					throw new \RuntimeException( $download_error );
				}
			}
			$uploadDir = wp_upload_dir();
			$file      = trailingslashit( $uploadDir['path'] ) . $locale . '.zip';
			// Save the zip file
			if ( ! $wp_filesystem->put_contents( $file, $response['body'], FS_CHMOD_FILE ) ) {
				throw new \RuntimeException( $download_error );
			}
			// Unzip the file to wp-content/languages/plugins directory
			$dir   = trailingslashit( WP_LANG_DIR ) . $this->type . 's/';
			$unzip = unzip_file( $file, $dir );
			if ( true !== $unzip ) {
				throw new \RuntimeException( $download_error );
			}
			// Delete the package file
			$wp_filesystem->delete( $file );

			return true;
		} else {
			throw new \RuntimeException( $download_error );
		}
	}

	/**
	 * Check if the language pack is avaliable in the language repo.
	 *
	 * @param string $locale locale
	 *
	 * @return bool true if exists , false otherwise
	 */
	public function is_available( $locale ) {
		$response = wp_remote_get(
			sprintf( '%s/%s.zip', $this->get_repository_url(), $locale ), array(
				'sslverify' => false,
				'timeout'   => 200
			)
		);

		if (
			! is_wp_error( $response ) &&
			( $response['response']['code'] >= 200 &&
			  $response['response']['code'] < 300 )
		) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Check if woocommerce language file is already downloaded.
	 *
	 * @param string $locale locale
	 *
	 * @return bool true if downloded , false otherwise
	 */
	public function is_downloaded( $locale ) {
		return file_exists( $this->get_local_path( $locale ) );
	}

	/**
	 * Returns local path
	 *
	 * @param $locale
	 *
	 * @return string
	 */
	public function get_local_path( $locale ) {
		return sprintf( trailingslashit( WP_LANG_DIR ) . '%s/%s-%s.mo', $this->type . 's', $this->slug, $locale );
	}

	/**
	 * Get language repo URL.
	 *
	 * @return string
	 */
	public function get_repository_url() {
		$url = sprintf( 'https://downloads.wordpress.org/translation/%s/%s/%s', $this->type, $this->slug, $this->version );
		return $url;
	}
}
