<?php
/**
 * @package Board Game Geek played games
 * @version 1.1 - Added widget
 * @version 1.0 - Initial package
 */
/*
Plugin Name: Board Game Geek played games
Plugin URI: http://takras.net/
Description: Show latest played games from Boardgamegeek
Author: Takras
Version: 1.1
Author URI: http://takras.net/
*/

function bgg_fetch_data( $enddate, $limit ) {

	// Check if settings are set
	if( get_option( 'bgg_username' ) == "" ) {
		echo "You need to configure this plugin. Go to Wordpress admin under settings for more information.";
		die();
	}
	
	// Get settings
	$bgg_username = get_option( 'bgg_username' );
	
	// URL variables
	$username = $bgg_username;
	$type_url = "&subtype=boardgame";

	$request= "http://bgg-json.azurewebsites.net/plays/$username";
	$response = file_get_contents( $request );
	$results = json_decode( $response, TRUE );
	bgg_display( $results, $limit );
}

// Display the HTML for the games
function bgg_display( $data, $requested_limit ) 
{

	// HTML structure
	$html = "
	<div class='bgg_game %5\$s'>
		<div class='image_box'>
			<a href='http://www.boardgamegeek.com/boardgame/%6\$s/' target='_new' title='%1\$s'><img src='%2\$s' alt='%1\$s'/></a>
		</div>
		<h3><a href='http://www.boardgamegeek.com/boardgame/%6\$s/' target='_new' title='%1\$s'>%1\$s</a></h3>
		<!--<span class='played_date'>%4\$s</span>-->
		<p>%3\$s</p>
	</div>";
	
	$datetitle = "<div class=\"dateheader %2\$s %3\$s\">%1\$s</div>";
	
	// Limit games showed
	$i = 0;
	if( $requested_limit == 0 ) {
		if( get_option( 'bgg_limit' ) != "" )
			$limit = 50; //get_option( 'bgg_limit');
		else
			$limit = 5;
	} else {
		$limit = $requested_limit;
	}
	
	echo "<div id='bgg_played'>";
	$curdate = '';
	foreach( $data as $key => $games ) {
	
		// Print out date for each datechange.
		if( $curdate != $games['playDate'] ) {
			$curdate = $games['playDate'];
			
			// Match background class, or in case of first post, add "first" class.
			echo sprintf( $datetitle, date_format( date_create( $games['playDate'] ), "d. M Y" ), $i % 2 == 0 ? "alt" : "", $i == 0 ? "first" : "" );
		}
		
		// Print out the HTML structure with data from Gamename, thumbnail url, comment, date and background color css class
		echo sprintf( $html, $games['name'], $games['thumbnail'], $games['comments'], date_format( date_create( $games['playDate'] ), "d. M Y" ), $i % 2 == 0 ? "alt" : "", $games['gameId'] );
		if( ++$i == $limit ) break;
	}
	echo "</div>";
	
}

// Thumbnail from BGG
function bgg_thumbnail( $gameid ) {
	$bgg = "http://www.boardgamegeek.com/xmlapi/boardgame/" . $gameid;
	$gameinfo = file_get_contents( $bgg );
	$xml = simplexml_load_string( $gameinfo );
	return $xml->boardgame->thumbnail;
}

// Initial function
function bgg_get_played_games( $attributes ) {
	if( !empty( $_GET['date'] ) ) {
		bgg_fetch_data( date( 'Y-m-d', strtotime( $_GET['date'] ) ), 0 );
	} else {
		bgg_fetch_data( date( 'Y-m-d' ), 0 );
	}
	return $attributes;
}

add_shortcode( 'bgg_played', 'bgg_get_played_games' );
wp_register_style( 'bggplayed', plugins_url( 'bggplayed/bggplayed.css' ) );
wp_enqueue_style( 'bggplayed' );

add_action( 'wp_head', 'bgg_stylesheet' );


/**
 * Administrative tools
 */
class BGGSettingsPage
{
    // Holds the values to be used in the fields callbacks
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
		
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    // Add options page
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_options_page(
            'BGG Played', 
            'BGG played Settings', 
            'manage_options', 
            'bgg-setting-admin', 
            array( $this, 'create_admin_page' )
        );
    }

    // Options page callback
    public function create_admin_page()
    {
	
        // Set class property
        $this->options = get_option( 'bgg_option_name' );
        ?>
        <div class="wrap">
            <h2>BGG played Settings</h2>           
            <form method="post" action="options-general.php?page=bgg-setting-admin">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'bgg_option_group' );   
                do_settings_sections( 'bgg-setting-admin' );
                submit_button(); 
            ?>
            </form>
        </div>
        <?php
    }

    // Register and add settings
    public function page_init()
    {        
        register_setting(
            'bgg_option_group', // Option group
            'bgg_option_name', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'bgg_section', // ID
            'BGG played Settings', // Title
            array( $this, 'print_section_info' ), // Callback
            'bgg-setting-admin' // Page
        );

        add_settings_field(
            'bgg_username',
            'BGG username',
            array( $this, 'username_callback' ),
            'bgg-setting-admin',
            'bgg_section'
        );

		add_settings_field(
            'bgg_limit',
            'Maximum number of games to display',
            array( $this, 'limit_callback' ),
            'bgg-setting-admin',
            'bgg_section'
        );

        add_settings_field(
            'bgg_limit_widget',
            'Maximum number of games to display for Widget',
            array( $this, 'widget_limit_callback' ),
            'bgg-setting-admin',
            'bgg_section'
        );
		
    }

    // Sanitize each setting field as needed
	// Note: Having some problems redirecting to correct setting page using Wordpress' recommended settings, so this is not called
    public function sanitize( $input )
    {
	
        $new_input = array();
		
        if( isset( $input['bgg_username'] ) ) {
            $new_input['bgg_username'] = sanitize_text_field( $input['bgg_username'] );
			update_option( 'bgg_username', $new_input['bgg_username'] );
		}
		
		if( isset( $input['bgg_stylesheet'] ) ) {
            $new_input['bgg_stylesheet'] = sanitize_text_field( $input['bgg_stylesheet'] );
			update_option( 'bgg_stylesheet', $new_input['bgg_stylesheet'] );
		}
		
		if( isset( $input['bgg_limit'] ) ) {
            $new_input['bgg_limit'] = sanitize_text_field( $input['bgg_limit'] );
			update_option( 'bgg_limit', $new_input['bgg_limit'] );
		}

		if( isset( $input['widget_bgg_limit'] ) ) {
            $new_input['widget_bgg_limit'] = sanitize_text_field( $input['widget_bgg_limit'] );
			update_option( 'widget_bgg_limit', $new_input['widget_bgg_limit'] );
		}
		
        return $new_input;
		
    }

    // Print the Section text
    public function print_section_info()
    {
        print 'Enter your settings below.';
    }

    public function username_callback()
    {
        printf(
            '<input type="text" id="bgg_username" name="bgg_option_name[bgg_username]" value="%s" />',
            get_option('bgg_username')
        );
    }
	
	public function limit_callback()
    {
        printf(
            '<input type="text" id="bgg_limit" name="bgg_option_name[bgg_limit]" value="%s" />',
            get_option('bgg_limit') != "" ? esc_attr( get_option('bgg_limit') ) : 5
        );
    }

    public function widget_limit_callback()
    {
        printf(
            '<input type="text" id="widget_bgg_limit" name="bgg_option_name[widget_bgg_limit]" value="%s" />',
            get_option('widget_bgg_limit') != "" ? esc_attr( get_option('widget_bgg_limit') ) : 4
        );
    }
}

if( is_admin() )
    $bgg_settings_page = new BGGSettingsPage();

// Manually save options, due to redirect-problems
function bgg_post() {
	if( isset( $_POST['option_page'] ) ){


		if(!isset($_POST['bgg_option_name']['bgg_username'])) { 
			$error_list[] = 'bgg_username';
		}

		//if( count($error_list) > 0 ) return $error_list;
	
		foreach((array)$_POST['bgg_option_name'] as $key => $value){
			update_option( $key, $value );
		}
		
	}
}
	
add_action('init', 'bgg_post');

/*
 * Widget
 */
 
class BGG_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'bgg_widget',
			'BGG played',
			array(
				'description' => 'Display the latest played games from a BoardGameGeek profile.'
			)
		);
	}

	// Front end
	public function widget( $args, $instance ) {
		$title = apply_filters( 'bgg_title', $instance['title'] );
		
		// Start
		echo $args['before_widget'];
		if ( ! empty( $title ) ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}
		
		// Main
		bgg_fetch_data( date( 'Y-m-d' ), get_option( 'widget_bgg_limit' ) );
		echo '<a class="moreplayed" href="http://aleajactaest.no/spilte-spill/" rel="bookmark">Se flere spill</a>';
		
		// End
		echo $args['after_widget'];
	}

	// Back end
	public function form( $instance ) {
		if ( isset( $instance[ 'title' ] ) ) {
			$title = $instance[ 'title' ];
		}
		else {
			$title = 'Sist spilte';
		}
		?>
		<p>
		<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		<?php
	}

	// Saving widget settings
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';

		return $instance;
	}
}

add_action( 'widgets_init', function() {
     register_widget( 'BGG_Widget' );
});?>