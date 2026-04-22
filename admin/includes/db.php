<?php
/**
 * Express News - Database Connection & Core Functions
 * This file is the single source of truth for database connections and helper functions.
 * It's included by other files to gain access to the database and utilities.
 */

// --- Error Reporting ---
// Useful for development to see all errors. Should be commented out or set to 0 for a live production website.
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// --- Database Configuration ---
// These constants define the connection details for your MySQL database.
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'express_news_db');

// --- Establish Database Connection ---
// This creates the $conn variable that all other scripts will use to interact with the database.
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check the connection and stop the entire application if it fails.
// This is a critical check to prevent the site from running with a broken database link.
if ($conn === false) {
    // In a live environment, you might log this error to a file instead of showing it to the user.
    die("FATAL ERROR: Could not connect to the database. " . mysqli_connect_error());
}

// --- Helper Functions ---

/**
 * Creates a URL-friendly "slug" from a string (e.g., a post title).
 * "My Awesome Post!" becomes "my-awesome-post".
 *
 * @param string $string The input string.
 * @return string The sanitized, lowercased, hyphenated slug.
 */
function create_slug($string) {
   // 1. Convert to lowercase
   $string = strtolower($string);
   // 2. Remove all characters that are not letters, numbers, spaces, or hyphens
   $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
   // 3. Replace all spaces and consecutive hyphens with a single hyphen
   $string = preg_replace('/[\s-]+/', '-', $string);
   // 4. Remove any leading or trailing hyphens
   return trim($string, '-');
}

/**
 * Checks whether core GD functions are available for image optimization.
 *
 * @return bool
 */
function is_gd_available() {
    return extension_loaded('gd')
        && function_exists('imagecreatetruecolor')
        && function_exists('imagecopyresampled')
        && function_exists('imagejpeg');
}

/**
 * Converts an IMAGETYPE_* constant to a file extension used in uploads.
 *
 * @param int $image_type
 * @return string
 */
function image_type_to_extension_name($image_type) {
    switch ($image_type) {
        case IMAGETYPE_JPEG:
            return 'jpg';
        case IMAGETYPE_PNG:
            return 'png';
        case IMAGETYPE_GIF:
            return 'gif';
        case IMAGETYPE_WEBP:
            return 'webp';
        default:
            return '';
    }
}

/**
 * Optimizes an uploaded image: resizes, compresses, and saves as both JPG and WebP.
 * This is the core function for performance optimization.
 *
 * @param string $source_path The temporary path of the uploaded file (e.g., $_FILES['tmp_name']).
 * @param string $destination_path_no_ext The full destination path WITHOUT the file extension (e.g., "../uploads/post_12345").
 * @param int $max_width The maximum width for the final image. It will be resized if wider.
 * @param int $quality The compression quality for JPEG/WebP images (0-100).
 * @return string|false The base filename (e.g., "post_12345") on success, or false on failure.
 */
function optimize_image($source_path, $destination_path_no_ext, $max_width = 1200, $quality = 75) {
    // Get image information (width, height, type)
    $image_info = getimagesize($source_path);
    if (!$image_info) { return false; } // Not a valid image

    list($width, $height, $type) = $image_info;
    $source_ext = image_type_to_extension_name($type);
    if (empty($source_ext)) {
        return false;
    }

    // Fallback mode for environments where GD is not enabled.
    // We keep uploads functional by storing the original image format.
    if (!is_gd_available()) {
        $fallback_path = $destination_path_no_ext . '.' . $source_ext;
        if (copy($source_path, $fallback_path)) {
            return basename($fallback_path);
        }
        return false;
    }

    // Calculate new dimensions while maintaining the aspect ratio
    $ratio = $height / $width;
    if ($width > $max_width) {
        $new_width = $max_width;
        $new_height = $max_width * $ratio;
    } else {
        $new_width = $width;
        $new_height = $height;
    }

    // Create a new blank, true color image in memory
    $new_image = imagecreatetruecolor($new_width, $new_height);

    // Create an image resource from the source file based on its type
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source_image = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $source_image = imagecreatefrompng($source_path);
            // Preserve transparency for PNGs when converting to WebP
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
            imagefill($new_image, 0, 0, $transparent);
            break;
        case IMAGETYPE_GIF:
            $source_image = imagecreatefromgif($source_path);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) {
                $source_image = imagecreatefromwebp($source_path);
                break;
            }
            $fallback_path = $destination_path_no_ext . '.webp';
            if (copy($source_path, $fallback_path)) {
                return basename($fallback_path);
            }
            return false;
        default:
            return false; // Unsupported image type
    }
    
    if (!$source_image) { return false; } // Could not create image from source
    
    // Copy the source image onto our new blank image, resizing it in the process
    imagecopyresampled($new_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

    // Define the final file paths with their new extensions
    $path_jpg = $destination_path_no_ext . '.jpg';
    $path_webp = $destination_path_no_ext . '.webp';

    // Save the new image in both formats
    $success_jpg = imagejpeg($new_image, $path_jpg, $quality);
    $success_webp = function_exists('imagewebp') ? imagewebp($new_image, $path_webp, $quality) : false;

    // Free up memory by destroying the image resources
    imagedestroy($source_image);
    imagedestroy($new_image);

    // Only return success if both images were created successfully
    if ($success_jpg && $success_webp) {
        // Return only the base filename (without directory or extension) to be stored in the database
        return basename($destination_path_no_ext);
    }

    // If WebP creation is unavailable/failed but JPG exists, keep the JPG instead of failing hard.
    if ($success_jpg) {
        return basename($path_jpg);
    }

    return false;
}

/**
 * Generates webp and jpg paths from a database image value.
 * This function is "smart" and handles both new (basename) and old (full path) formats.
 *
 * @param string|null $db_image_value The value from the featured_image column.
 * @return array An array with 'webp' and 'jpg' keys containing full, usable image paths.
 */
function getImagePaths($db_image_value) {
    // Define a default/placeholder image in case the provided value is empty.
    // IMPORTANT: You should create a simple placeholder image and save it here.
    $default_image_path = '/express-news/assets/images/placeholder.jpg';
    $default_image_path_webp = '/express-news/assets/images/placeholder.webp';

    if (empty($db_image_value)) {
        return ['webp' => $default_image_path_webp, 'jpg' => $default_image_path];
    }

    $uploads_abs_dir = __DIR__ . '/../../uploads/';
    $uploads_public_dir = '/express-news/uploads/';
    $normalized_value = ltrim(str_replace('\\', '/', trim($db_image_value)), '/');
    $image_file_name = basename($normalized_value);

    // Get the base filename without any extension or preceding directories.
    // This is the key part that handles both "post_123" and "uploads/post_abc.jpg"
    $image_base = pathinfo($image_file_name, PATHINFO_FILENAME);
    $image_ext = strtolower(pathinfo($image_file_name, PATHINFO_EXTENSION));
    
    // If pathinfo returns an empty string (can happen with invalid input), fall back to default.
    if (empty($image_base)) {
        return ['webp' => $default_image_path_webp, 'jpg' => $default_image_path];
    }

    $jpg_abs = $uploads_abs_dir . $image_base . '.jpg';
    $webp_abs = $uploads_abs_dir . $image_base . '.webp';

    // Standard optimized case: both JPG + WEBP exist.
    if (file_exists($jpg_abs) || file_exists($webp_abs)) {
        $jpg_url = file_exists($jpg_abs) ? $uploads_public_dir . $image_base . '.jpg' : $default_image_path;
        $webp_url = file_exists($webp_abs) ? $uploads_public_dir . $image_base . '.webp' : $jpg_url;
        return ['webp' => $webp_url, 'jpg' => $jpg_url];
    }

    // Fallback case: original file exists with extension (used when GD is unavailable).
    if (!empty($image_ext)) {
        $direct_abs = $uploads_abs_dir . $image_file_name;
        if (file_exists($direct_abs)) {
            $direct_url = $uploads_public_dir . $image_file_name;
            return ['webp' => $direct_url, 'jpg' => $direct_url];
        }
    }
    
    // Construct the final, root-relative paths that will work in HTML.
    return ['webp' => $default_image_path_webp, 'jpg' => $default_image_path];
}

/**
 * Deletes known image variants for a stored post image value.
 * Supports values stored as basename (optimized pair) or filename with extension.
 *
 * @param string|null $db_image_value
 * @param string|null $upload_dir
 * @return void
 */
function delete_post_image_files($db_image_value, $upload_dir = null) {
    if (empty($db_image_value)) {
        return;
    }

    $upload_dir = $upload_dir ?: (__DIR__ . '/../../uploads/');
    $upload_dir = rtrim($upload_dir, '/\\') . DIRECTORY_SEPARATOR;

    $normalized_value = str_replace('\\', '/', trim($db_image_value));
    $file_name = basename($normalized_value);
    $base_name = pathinfo($file_name, PATHINFO_FILENAME);
    $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    $candidate_paths = [
        $upload_dir . $base_name . '.jpg',
        $upload_dir . $base_name . '.webp',
        $upload_dir . $base_name . '.jpeg',
        $upload_dir . $base_name . '.png',
        $upload_dir . $base_name . '.gif'
    ];

    if (!empty($extension)) {
        $candidate_paths[] = $upload_dir . $file_name;
    }

    foreach (array_unique($candidate_paths) as $path) {
        if (file_exists($path)) {
            @unlink($path);
        }
    }
}

?>