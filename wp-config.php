<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wp_shared' );

/** Database username */
define( 'DB_USER', 'wpinstall' );

/** Database password */
define( 'DB_PASSWORD', 'putangina1337!' );

/** Database hostname */
define( 'DB_HOST', '212.22.70.209' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'V&V;QvlktAYEj_DA/|8kjE<5jBxp}w[n$^g;/Su^+D1pN{nWn$L#ML{LXIe|l Py' );
define( 'SECURE_AUTH_KEY',  'ZGU=Uj*00k?I0+e([$y_uwwW1dyJ`JprGo-vFwE}g4hJwXs+L?v-In+MHu[Q$8:2' );
define( 'LOGGED_IN_KEY',    'n+2Bb^62mruNXv[Wr[eIT(w|}1sU+.VG2Aqe3} *dGPtdn;bLh5/2ZwfnRq}+Uq&' );
define( 'NONCE_KEY',        'f|s3V%0!bEnyrNm-f`%GApQMb3Pe@X5W4ul:g1,6Y,({b>RCjn?f#R/PqGM23Z_D' );
define( 'AUTH_SALT',        'L06Hxlc;9jp]{^3/mv_;{*]^KT9KJ3J5M]ASUK%5Q@E1*b`8t {u_fn1tSF9-4K!' );
define( 'SECURE_AUTH_SALT', ';gt1Kqp*faq$0s(!u8O[xC8OqMbwpOZhZdEO:nm&&:-!c2OxS#`003mt,9!H.`^Q' );
define( 'LOGGED_IN_SALT',   '4=5)RZ:H[#;25(V(/ppm(@MeY?90<<:4{Dd2FopuM&M8pN9~m4%7g)`5t::hLrag' );
define( 'NONCE_SALT',       '`^GsdImsKte?7D]`Y~T.i0?/]RJ9n6i5nxG%{$,~kOP)9G=fT7dAD?MGrxx99,fi' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_1049121922371463';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

