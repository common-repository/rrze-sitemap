<?php
/**
 * Plugin Name: RRZE-Sitemap
 * Description: Automatische Generierung eines XML-Sitemap.
 * Version: 1.3
 * Author: rvdforst
 * Author URI: http://blogs.fau.de/webworking/
 * License: GPLv2 or later
 */

/*
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

add_action( 'plugins_loaded', array( 'RRZE_Sitemap', 'init' ) );

register_activation_hook( __FILE__, array( 'RRZE_Sitemap', 'activation' ) );

class RRZE_Sitemap {

    const name = 'RRZE-Sitemap'; // Plugin-Name
    
    const version = '1.3'; // Plugin-Version

    const option_name = '_rrze_sitemap';

    const version_option_name = '_rrze_sitemap_version';
    
    const textdomain = '_rrze_sitemap';
    
    const php_version = '5.2.4'; // Minimal erforderliche PHP-Version
    
    const wp_version = '3.4.1'; // Minimal erforderliche WordPress-Version
    
    public static function init() {
        
        load_plugin_textdomain( self::textdomain, false, sprintf( '%slang', plugin_dir_path( __FILE__ ) ) );
        
        add_action( 'admin_menu', array( __CLASS__, 'add_options_page' ) );

        add_action( 'admin_init', array( __CLASS__, 'settings_init' ) );

        add_action( 'init', array( __CLASS__, 'add_filters' ), 100 );

    }

    public static function add_filters() {
        
        if( ! is_multisite() || is_subdomain_install() || self::is_base_site() )
            add_filter( 'robots_txt', array( __CLASS__, 'robots_txt_filter' ), 10, 2 );
        
		add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ), 10, 1 );

		add_filter( 'template_redirect', array( __CLASS__, 'add_template_redirect' ), 10 );

		add_filter( 'parse_request', array( __CLASS__, 'empty_parse_request' ), 10 );

        add_filter( 'rewrite_rules_array', array( __CLASS__, 'add_rewrite_rules' ), 10, 1 );

		if( get_option( self::version_option_name, null) != self::version ) {
			self::flush_rewrite();
		}        
    }
    
    public static function activation() {        
        self::version_compare();
        
        add_filter( 'rewrite_rules_array', array( __CLASS__, 'add_rewrite_rules' ), 1, 1 );
        self::flush_rewrite();
    }
        
    public static function version_compare() {
        $error = '';
        
        if ( version_compare( PHP_VERSION, self::php_version, '<' ) ) {
            $error = sprintf( __('Ihre PHP-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die PHP-Version %s.', self::textdomain ), PHP_VERSION, self::php_version );
        }

        if ( version_compare( $GLOBALS['wp_version'], self::wp_version, '<' ) ) {
            $error = sprintf( __('Ihre Wordpress-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die Wordpress-Version %s.', self::textdomain ), $GLOBALS['wp_version'], self::wp_version );
        }

        if( ! empty( $error ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ), false, true );
            wp_die( $error );
        }
        
    }
        
    private static function default_options() {
        $options = array(
            'general' => array(
                'robots_txt' => array(
                    'value' => true,
                    'label' => __( 'Sitemap-Anweisung zum robots.txt Inhalt hinzufügen.' )
                )                
            ),
            
            'include' => array(
                'home' => array(
                    'value' => true,
                    'label' => __( 'Startseite', self::textdomain )
                ),
                'posts' => array(
                    'value' => true,
                    'label' => __( 'Beiträge', self::textdomain )
                ),        
                'pages' => array(
                    'value' => true,
                    'label' => __( 'Statische Seiten', self::textdomain )
                ),
                'cats' => array(
                    'value' => false,
                    'label' => __( 'Kategorien', self::textdomain )
                ),
                'archives' => array(
                    'value' => false,
                    'label' => __( 'Archive', self::textdomain )
                ),
                'authors' => array(
                    'value' => false,
                    'label' => __( 'Autorenseiten', self::textdomain )
                ),
                'tags' => array(
                    'value' => false,
                    'label' => __( 'Tag-Seiten', self::textdomain )
                )          
            ),
            
            'change_frequency' => array(
                'home' => array(
                    'value' => 'daily',
                    'label' => __( 'Startseite', self::textdomain )
                ),
                'posts' => array(
                    'value' => 'monthly',
                    'label' => __( 'Beiträge', self::textdomain )
                ),        
                'pages' => array(
                    'value' => 'weekly',
                    'label' => __( 'Statische Seiten', self::textdomain )
                ),
                'cats' => array(
                    'value' => 'weekly',
                    'label' => __( 'Kategorien', self::textdomain )
                ),
                'archives_current' => array(
                    'value' => 'daily',
                    'label' => __( 'Das Archive des aktuellen Monats', self::textdomain )
                ),
                'archives_old' => array(
                    'value' => 'yearly',
                    'label' => __( 'Archive der vergangenen Monate', self::textdomain )
                ),
                'authors' => array(
                    'value' => 'weekly',
                    'label' => __( 'Autorenseiten', self::textdomain )
                ),
                'tags' => array(
                    'value' => 'weekly',
                    'label' => __( 'Tag-Seiten', self::textdomain )
                )
            ),
            
            'priority' => array(
                'home' => array(
                    'value' => '1.0',
                    'label' => __( 'Startseite', self::textdomain )
                ),
                'posts' => array(
                    'value' => '0.6',
                    'label' => __( 'Beiträge', self::textdomain )
                ),        
                'pages' => array(
                    'value' => '0.6',
                    'label' => __( 'Statische Seiten', self::textdomain )
                ),
                'cats' => array(
                    'value' => '0.3',
                    'label' => __( 'Kategorien', self::textdomain )
                ),
                'archives' => array(
                    'value' => '0.3',
                    'label' => __( 'Archive', self::textdomain )
                ),
                'authors' => array(
                    'value' => '0.3',
                    'label' => __( 'Autorenseiten', self::textdomain )
                ),
                'tags' => array(
                    'value' => '0.3',
                    'label' => __( 'Tag-Seiten', self::textdomain )
                )                
            )            
        );
        
        return $options;
    }

    public static function get_options( $key = '' ) {
        $defaults = self::default_options();
        
        foreach( $defaults as $ky => $options ) {
            
            foreach( $options as $k => $option ) {
                $defaults[$ky][$k] = $option['value'];
            }

        }
        
        $options = (array) get_option( self::option_name );
        $options = wp_parse_args( $options, $defaults );
        $options = array_intersect_key( $options, $defaults );
        
        if( ! empty( $key ) ) {
            $keys = (array) explode( ':', $key );
            if( ! isset( $keys[1]))
                return isset( $options[$key] ) ? $options[$key] : null;
            else
                return isset( $options[$keys[0]][$keys[1]] ) ? $options[$keys[0]][$keys[1]] : null;
        }
        
        return $options;
    }
        
    private static function get_default_options( $key = '' ) {
        $defaults = self::default_options();
        
        if( ! empty( $key ) )
            return isset( $defaults[$key] ) ? $defaults[$key] : null;
            
        return $defaults;
    }
    
	public static function add_options_page() {

		add_options_page( __( 'Sitemap', self::option_name ), __( 'Sitemap', self::option_name ), 'manage_options', 'options-sitemap', array( __CLASS__, 'options_page' ) );

	}
    
    public static function options_page() {
        ?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2><?php echo esc_html( __( 'Einstellungen &rsaquo; Sitemap', self::textdomain ) ); ?></h2>
            
            <form method="post" action="options.php">
                <?php 
                settings_fields( 'sitemap_options' );
                do_settings_sections( 'sitemap_options' );
                submit_button();
                ?>
            </form>
            
        </div>
        <?php
        
    }
    
    public function settings_init() {
        register_setting( 'sitemap_options', self::option_name, array( __CLASS__, 'options_validate' ) );
        
        if( ! is_multisite() || is_subdomain_install() || self::is_base_site() ) {
            add_settings_section( 'options_general_section', __('Allgemeine Einstellungen', self::textdomain ), array( __CLASS__, 'section_options_general' ), 'sitemap_options' );
       
            add_settings_field( 'options_general', __( 'Datei robots.txt', self::textdomain ), array( __CLASS__, 'field_options_general' ), 'sitemap_options', 'options_general_section' );
        }
        
        add_settings_section( 'options_include_section', __('Sitemap-Inhalt', self::textdomain ), array( __CLASS__, 'section_options_include' ), 'sitemap_options' );
        
        add_settings_field( 'options_include', __( 'Standard Inhalt', self::textdomain ), array( __CLASS__, 'field_options_include' ), 'sitemap_options', 'options_include_section' );
    }
    
    public static function section_options_general() {
        printf('<p>%s</p>', __( 'Wählen Sie, welche Optionen aktivieren möchten.', self::textdomain ));
    }
    
    public static function field_options_general() {
        $defaults = self::get_default_options( 'general' );
        $options = self::get_options( 'general' );    
        ?>
        <ul>
        <?php foreach( $defaults as $key => $option ): ?>
            <li>
                <label for="<?php echo $key; ?>"><input type="checkbox" <?php checked( $options[$key], true ); ?> name="<?php printf( '%s[general][%s]', self::option_name, $key ); ?>" id="<?php echo $key; ?>" /> <?php echo $option['label']; ?></label>
            </li>
        <?php endforeach; ?>
        </ul>
        <?php
    }
    
    public static function section_options_include() {
        printf('<p>%s</p>', __( 'Wählen Sie, welchen Inhalt aktivieren möchten.', self::textdomain ));
    }
    
    public static function field_options_include() {
        $defaults = self::get_default_options( 'include' );
        $options = self::get_options( 'include' );
        ?>
        <ul>
        <?php foreach( $defaults as $key => $option ): ?>
            <li>
                <label for="<?php echo $key; ?>"><input type="checkbox" <?php checked( $options[$key], true ); ?> name="<?php printf( '%s[include][%s]', self::option_name, $key ); ?>" id="<?php echo $key; ?>" /> <?php echo $option['label']; ?></label>
            </li>
        <?php endforeach; ?>
        </ul>
        <?php
    }

    public static function options_validate( $input ) {
        $options = self::get_options();

        foreach( $options['general'] as $key => $value ) {
            if( isset( $input['general'][$key] ) )
                $options['general'][$key] = true;
            else
                $options['general'][$key] = false;

        }

        foreach( $options['include'] as $key => $value ) {
            if( isset( $input['include'][$key] ) )
                $options['include'][$key] = true;
            else
                $options['include'][$key] = false;

        }
        
        return $options;
    }

	private static function flush_rewrite() {
		global $wp_rewrite;
        
		$wp_rewrite->flush_rules( false );
		update_option( self::version_option_name , self::version );
	}
    
	public static function add_rewrite_rules( $wp_rules ) {
		$sitemap_rules = array(
			'sitemap(-+([a-zA-Z0-9_-]+))?\.xml$' => 'index.php?xml_sitemap=params=$matches[2]'
		);
		return array_merge( $sitemap_rules, $wp_rules );
	}
    
    public static function robots_txt_filter( $robots_txt, $public ) {
        $robots = self::get_options( 'general:robots_txt' );
        if( $robots && $public != 0 ) {
            $robots_txt .= sprintf( '%2$sSitemap: %s%2$s' , self::xml_url(), PHP_EOL );
        }
        return $robots_txt;        
    }

	public static function add_query_vars( $vars ) {
		array_push( $vars, 'xml_sitemap' );
		return $vars;
	}
    
	public static function add_template_redirect() {
		global $wp_query;
        
		if( ! empty( $wp_query->query_vars['xml_sitemap'] ) ) {
			$wp_query->is_404 = false;
			$wp_query->is_feed = false;
			self::sitemap_output( $wp_query->query_vars['xml_sitemap'] );
		}
	}
    
	public static function empty_parse_request() {
		add_filter( 'posts_request', array( __CLASS__, 'empty_posts_request' ), 100, 2);
	}

	public static function empty_posts_request( $sql, $query ) {
		if( ! empty( $query->query_vars['xml_sitemap'] ) ) {
			remove_filter( 'posts_request', array( __CLASS__, 'empty_posts_request' ), 100, 2 );
            
			$query->query_vars['no_found_rows'] = true;
			$query->is_home = false;
			$query->is_404 = true;
            
			return "SELECT SQL_CALC_FOUND_ROWS ID FROM {$GLOBALS['wpdb']->posts} WHERE ID = 0";
		}
		return $sql;
	}
    
	public static function add_sitemap( $type, $params = '', $last_mod = 0 ) {

		$xml_url = self::xml_url( $type, $params );

		self::render_index_element( $xml_url, $last_mod );
	}
    
	public static function add_url( $loc, $last_mod = 0, $change_frequency = "monthly", $priority = 0.5, $post_id = 0 ) {
        
		self::render_content_element( $loc, $priority, $change_frequency, $last_mod, $post_id );
        
	}
    
	private static function xml_url( $type = '', $params = '' ) {
		global $wp_rewrite;

		$mod_rewrite = $wp_rewrite->using_mod_rewrite_permalinks();
        
		$options = '';
		if( ! empty( $type ) ) {
			$options .= $type;
			if( ! empty( $params ) ) {
				$options .= '-' . $params;
			}
		}

		if( $mod_rewrite ) {
			return trailingslashit( get_bloginfo( 'url' ) ) . 'sitemap' . ( $options ? '-' . $options : '' ) . '.xml';
		} else {
			return trailingslashit( get_bloginfo( 'url' ) ) . 'index.php?xml_sitemap=params=' . $options;
		}
	}
        
	public static function get_post_types() {
		$all_post_types = get_post_types();
		$enabled_post_types = array();
        
		if( self::get_options( 'include:posts' ) ) 
            $enabled_post_types[] = 'post';
        
		if( self::get_options( 'include:pages' ) ) 
            $enabled_post_types[] = 'page';

		$post_types = array();
		foreach( $enabled_post_types as $post_type ) {
			if( ! empty( $post_type ) && in_array( $post_type, $all_post_types ) ) {
				$post_types[] = $post_type;
			}
		}

		return $post_types;
	}
    
	private static function sitemap_output( $options ) {
		$options_arry = array();

		$options = explode( ';', $options );
		foreach( $options as $k ) {
			$kv = explode( '=', $k );
			$options_arry[$kv[0]] = @$kv[1];
		}

		$options = $options_arry;

		$ob_gzhandler = true;
		if( empty( $_SERVER['HTTP_ACCEPT_ENCODING'] ) || is_null( strpos( 'gzip', $_SERVER['HTTP_ACCEPT_ENCODING'] ) ) || ! function_exists( 'gzwrite' ) || headers_sent()) 
            $ob_gzhandler = false;
		
        if( $ob_gzhandler ) 
            ob_start('ob_gzhandler');

        header( 'Content-Type: application/xml; charset=utf-8' );
        
		if( empty( $options['params'] ) || $options['params'] == 'index' ) {

			self::sitemap_header( 'index' );

			self::sitemap_index();

			self::sitemap_footer( 'index' );

		} else {
			$all_params = $options['params'];
			$type = $params = null;
			if( strpos( $all_params, '-' ) !== false ) {
				$type = substr( $all_params, 0, strpos( $all_params, '-' ) );
				$params = substr( $all_params, strpos( $all_params, '-' ) + 1 );
			} else {
				$type = $all_params;
			}

			self::sitemap_header( 'sitemap' );

            self::sitemap_content( $type, $params );

			self::sitemap_footer( 'sitemap' );

		}

		if( $ob_gzhandler ) 
            ob_end_flush();
        
		exit;
	}

	private static function sitemap_header( $format ) {

		if( ! in_array( $format, array( 'sitemap', 'index' ) ) ) 
            $format = 'sitemap';

		echo '<?xml version="1.0" encoding="UTF-8"' . '?' . '>';

        printf( '<!-- generator="%s Version %s" -->%s', self::name, self::version, PHP_EOL );

		switch($format) {
			case 'sitemap':
				echo '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
				break;
            
			case 'index':
				echo '<sitemapindex xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/siteindex.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
				break;
		}
        
	}

	private static function sitemap_footer( $format ) {
		if( ! in_array( $format, array( 'sitemap', 'index' ) ) ) 
                $format = 'sitemap';
        
		switch($format) {
			case 'sitemap':
				echo '</urlset>';
				break;
            
			case 'index':
				echo '</sitemapindex>';
				break;
		}
	}
    
	private static function render_index_element( $url = '', $last_mod = 0 ) {
        $url = (string) $url;
        $last_mod = intval( $last_mod );
        
		if( $url == "/" || empty( $url ) ) return '';

		$str = "";
		$str .= "\t<sitemap>" . PHP_EOL;
		$str .= "\t\t<loc>" . self::escape_xml( $url ) . "</loc>" . PHP_EOL;
		
        if( $last_mod > 0 ) 
            $str .= "\t\t<lastmod>" . date( 'Y-m-d\TH:i:s+00:00', $last_mod ) . "</lastmod>" . PHP_EOL;
		
        $str .= "\t</sitemap>" . PHP_EOL;;
		echo $str;
	}
    
	private static function render_content_element( $url = '', $priority = 0.0, $change_frequency = 'never', $last_mod = 0, $post_id = 0 ) {
        $url = (string) $url;
        $priority = floatval( $priority );
        $last_mod = intval( $last_mod );
        $change_frequency = (string) $change_frequency;
        $post_id = intval( $post_id );
        
		if( $url == '/' || empty( $url ) ) return '';

		$str = "";
		$str .= "\t<url>" . PHP_EOL;
		$str .= "\t\t<loc>" . self::escape_xml( $url ) . "</loc>" . PHP_EOL;
		if( $last_mod > 0 ) 
            $str .= "\t\t<lastmod>" . date('Y-m-d\TH:i:s+00:00', $last_mod) . "</lastmod>" . PHP_EOL;
        
		if( ! empty($change_frequency)) 
            $str .= "\t\t<changefreq>" . $change_frequency . "</changefreq>" . PHP_EOL;
        
		if( ! empty( $priority ) ) 
            $str .= "\t\t<priority>" . number_format( $priority, 1 ) . "</priority>" . PHP_EOL;
        
		$str .= "\t</url>\n";
		echo $str;
	}
    
	private static function escape_xml( $str ) {
		return str_replace( array( '&', '"', "'", '<', '>' ), array( '&amp;', '&quot;', '&apos;', '&lt;', '&gt;' ), $str );
	}
    
	public static function sitemap_index() {
		global $wpdb;

		$last_post_date = strtotime( get_lastpostdate( 'blog' ) );

		self::add_sitemap( 'front', null, $last_post_date );

		if( self::get_options( 'include:archives' ) ) 
            self::add_sitemap( 'archives', null, $last_post_date );
        
		if( self::get_options( 'include:authors' ) ) 
            self::add_sitemap( 'authors', null, $last_post_date );

		$taxonomies = self::get_enabled_taxonomies();
		foreach( $taxonomies as $tax ) {
			self::add_sitemap( 'tax', $tax );
		}

		$enabled_post_types = self::get_post_types();

		if( count( $enabled_post_types) > 0) {

			foreach( $enabled_post_types as $post_type ) {

				$sql = "
					SELECT
						YEAR(p.post_date_gmt) AS `year`,
						MONTH(p.post_date_gmt) AS `month`,
						COUNT(p.ID) AS `numposts`,
						MAX(p.post_date_gmt) as last_mod
					FROM
						{$wpdb->posts} p
					WHERE
						p.post_password = ''
						AND p.post_type = '" . $wpdb->escape( $post_type ) . "'
						AND p.post_status = 'publish'
					GROUP BY
						YEAR(p.post_date_gmt),
						MONTH(p.post_date_gmt)
					ORDER BY
						p.post_date DESC";

				$posts = $wpdb->get_results( $sql );

				if( $posts ) {
					foreach( $posts as $post ) {
						self::add_sitemap( 'type', $post_type . "-" . sprintf( '%04d-%02d', $post->year, $post->month ), strtotime( $post->last_mod ) );
					}
				}
			}
		}
	}
    
	public static function sitemap_content( $type, $params ) {

		switch( $type ) {
			case 'front':
				self::build_front();
				break;
            
			case 'type':
				self::build_posts( $type, $params );
				break;
            
			case 'archives':
				self::build_archives();
				break;
            
			case 'authors':
				self::build_authors();
				break;
            
			case 'tax':
				self::build_taxonomies( $params );
				break;
            
		}
	}

	public static function password_protected_filter( $where ) {
		global $wpdb;
        
		$where .= "AND ($wpdb->posts.post_password = '') ";
		return $where;
	}

	public static function fields_filter( $fields ) {
		global $wpdb;

		$default_fields = array(
			$wpdb->posts . '.ID',
			$wpdb->posts . '.post_author',
			$wpdb->posts . '.post_date',
			$wpdb->posts . '.post_date_gmt',
			$wpdb->posts . '.post_content',
			$wpdb->posts . '.post_title',
			$wpdb->posts . '.post_excerpt',
			$wpdb->posts . '.post_status',
			$wpdb->posts . '.post_name',
			$wpdb->posts . '.post_modified',
			$wpdb->posts . '.post_modified_gmt',
			$wpdb->posts . '.post_content_filtered',
			$wpdb->posts . '.post_parent',
			$wpdb->posts . '.guid',
			$wpdb->posts . '.post_type', 
            'post_mime_type',
			$wpdb->posts . '.comment_count'
		);

		$fields = implode( ', ', $default_fields );
		return $fields;
	}

	private static function build_posts( $type, $params ) {

		if( ! $post_types = strpos( $params, '-') ) 
            return;

		$post_type = substr( $params, 0, $post_types );

		if( ! in_array($post_type, self::get_post_types() ) ) 
            return;

		$params = substr( $params, $post_types + 1 );

		if( preg_match( '/^([0-9]{4})\-([0-9]{2})$/', $params, $matches ) ) {
			$year = $matches[1];
			$month = $matches[2];

			$query = self::build_post_query( $post_type );

			$query['year'] = $year;
			$query['monthnum'] = $month;

			$struct = get_option( 'permalink_structure' );
			if( strpos($struct, '%category%' ) === false && strpos( $struct, '%tag%' ) == false ) {
				$query['update_post_term_cache'] = false;
			}

			$query['update_post_meta_cache'] = false;

			add_filter( 'posts_search', array( __CLASS__, 'password_protected_filter' ), 10, 1 );
			add_filter( 'posts_fields', array( __CLASS__, 'fields_filter' ), 10, 1 );

			$posts = get_posts( $query );

			remove_filter( 'posts_where', array( __CLASS__, 'password_protected_filter' ), 10, 1 );
			remove_filter( 'posts_fields', array( __CLASS__, 'fields_filter' ), 10, 1 );

			$post_count = count( $posts );
            if( $post_count > 0 ) {

				$priority_posts = self::get_options( 'priority:posts' );
				$priority_pages = self::get_options( 'priority:pages' );

				$change_frequency_pages = self::get_options( 'change_frequency:pages' );
				$change_frequency_posts = self::get_options( 'change_frequency:posts' );

				$home_pid = 0;
				$home = get_bloginfo('url');
				if( 'page' == get_option( 'show_on_front' ) && get_option( 'page_on_front' ) ) {
					$page_on_front = get_option( 'page_on_front' );
					$page = get_page( $page_on_front );
					if( $page ) 
                        $home_pid = $page->ID;
				}

				foreach( $posts as $post ) {
					$permalink = get_permalink( $post->ID );

					if( ! empty( $permalink ) && $permalink != $home && $post->ID != $home_pid && $permalink != '#' ) {

						$priority = ( $post_type == 'page' ? $priority_pages : $priority_posts );

						self::add_url( 
                            $permalink, 
                            strtotime( ( $post->post_modified_gmt && $post->post_modified_gmt != '0000-00-00 00:00:00' ? $post->post_modified_gmt : $post->post_date_gmt ) ), 
                            $post_type == 'page' ? $change_frequency_pages : $change_frequency_posts, 
                            $priority, $post->ID 
                        );

					}
				}
			}
		}
	}

	private static function build_archives() {
		global $wpdb;
        
		$now = current_time( 'mysql' );

		$archives = $wpdb->get_results( "
			SELECT DISTINCT
				YEAR(post_date_gmt) AS `year`,
				MONTH(post_date_gmt) AS `month`,
				MAX(post_date_gmt) as last_mod,
				count(ID) as posts
			FROM
				$wpdb->posts
			WHERE
				post_date < '$now'
				AND post_status = 'publish'
				AND post_type = 'post'
			GROUP BY
				YEAR(post_date_gmt),
				MONTH(post_date_gmt)
			ORDER BY
				post_date_gmt DESC
		" );

		if( $archives ) {
			foreach( $archives as $archive ) {

				$url = get_month_link( $archive->year, $archive->month );
				$change_frequency = '';

				if( $archive->month == date( 'n' ) && $archive->year == date( 'Y' ) ) {
					$change_frequency = self::get_options( 'change_frequency:archives_current' );
				} else {
					$change_frequency = self::get_options( 'change_frequency:archives_old' );
				}

				self::add_url( $url, strtotime( $archive->last_mod ), $change_frequency, self::get_options( 'priority:archives' ) );
			}
		}
	}

	private static function build_front() {

		if( self::get_options( 'include:home' ) ) {
			$home = get_bloginfo( 'url' );
			$home_pid = 0;

            if( 'page' == get_option( 'show_on_front' ) && get_option( 'page_on_front' ) ) {
                $page_on_front = get_option( 'page_on_front' );
                $page = get_page( $page_on_front );
                if( $page ) {
                    $home_pid = $page->ID;
                    self::add_url( 
                        trailingslashit($home), 
                        strtotime( $page->post_modified_gmt && $page->post_modified_gmt != '0000-00-00 00:00:00' ? $page->post_modified_gmt : $page->post_date_gmt ), 
                        self::get_options( 'change_frequency:home' ), 
                        self::get_options( 'priority:home' ) 
                    );
                }
            } else {
                $last_mod = get_lastpostmodified( 'GMT' );
                self::add_url( 
                    trailingslashit( $home ), 
                    $last_mod ? strtotime( $last_mod ) : time(), 
                    self::get_options( 'change_frequency:home' ), 
                    self::get_options( 'priority:home' ) 
                );
            }

		}

	}

	private static function build_authors() {
		global $wpdb;

		$sql = "SELECT DISTINCT
					u.ID,
					u.user_nicename,
					MAX(p.post_modified_gmt) AS last_post
				FROM
					{$wpdb->users} u,
					{$wpdb->posts} p
				WHERE
					p.post_author = u.ID
					AND p.post_status = 'publish'
					AND p.post_type = 'post'
					AND p.post_password = ''
				GROUP BY
					u.ID,
					u.user_nicename";

		$authors = $wpdb->get_results( $sql );

		if( $authors && is_array( $authors ) ) {
			foreach( $authors as $author ) {
				$url = get_author_posts_url( $author->ID, $author->user_nicename );
				self::add_url( $url, strtotime( $author->last_post ), self::get_options( 'change_frequency:author' ), self::get_options( 'priority:author' ) );
			}
		}
	}


	public static function terms_query_filter( $selects, $args ) {
		global $wpdb;
        
		$selects[] = "
            ( SELECT
				UNIX_TIMESTAMP(MAX(p.post_date_gmt)) as _mod_date
			  FROM
				{$wpdb->posts} p,
				{$wpdb->term_relationships} r
			  WHERE
				p.ID = r.object_id
				AND p.post_status = 'publish'
				AND p.post_password = ''
				AND r.term_taxonomy_id = tt.term_taxonomy_id
		    ) as _mod_date";

		return $selects;
	}

	private static function build_taxonomies( $taxonomy ) {
		$enabled_taxonomies = self::get_enabled_taxonomies();
		if( in_array( $taxonomy, $enabled_taxonomies ) ) {

			$excludes = array();

			if($taxonomy == "category") {
				$exclude_cats = array();
				if( $exclude_cats ) 
                    $excludes = $exclude_cats;
			}

			add_filter( 'get_terms_fields', array( __CLASS__, 'terms_query_filter' ), 20, 2 );
            
			$terms = get_terms( $taxonomy, array( 'hide_empty' => true, 'hierarchical' => false, 'exclude' => $excludes ) );
			remove_filter( 'get_terms_fields', array( __CLASS__, 'terms_query_filter' ), 20, 2 );

			foreach( $terms AS $term ) {
				self::add_url( get_term_link( $term, $term->taxonomy ), $term->_mod_date, self::get_options( 'change_frequency:tags' ), self::get_options( 'priority:tags' ) );
			}
		}
	}

	private static function get_enabled_taxonomies() {
		$enabled_taxonomies = array();
		
        if( self::get_options( 'include:tags' ) ) 
            $enabled_taxonomies[] = 'post_tag';
        
		if( self::get_options( 'include:cats' ) ) 
            $enabled_taxonomies[] = 'category';

		$tax_list = array();
		foreach( $enabled_taxonomies as $tax_name ) {
			$taxonomy = get_taxonomy( $tax_name );
			if( $taxonomy && wp_count_terms( $taxonomy->name, array( 'hide_empty' => true ) ) >0 ) $tax_list[] = $taxonomy->name;
		}
        
		return $tax_list;
	}

	private static function build_post_query( $post_type ) {
		$post_query = array(
			'post_type' => $post_type,
			'numberposts' => 0,
			'nopaging' => true,
			'suppress_filters' => false
		);

		$excludes = array();

		if( $post_type == 'page' && get_option( 'show_on_front' ) == 'page' && get_option( 'page_on_front' ) ) {
			$excludes[] = get_option( 'page_on_front' );
		}

		if( count( $excludes) > 0 ) {
			$post_query['post__not_in'] = $excludes;
		}

		$exclude_cats = array();

		if( count($exclude_cats) > 0 ) {
			$post_query['category__not_in'] = $exclude_cats;
		}

		return $post_query;
	}
    
    private static function is_base_site() {
        global $current_site, $current_blog;
        
        if( $current_site->path == $current_blog->path )
            return true;
        
        return false;
    }
    
}
