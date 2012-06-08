<?php
/*
Plugin Name: sh-twitter
Description: Creates a recent tweets widget. Based on WPTuts article
Version: 1.0
Author: Stephen Harris
Author URI: stephenharris.info
*/


class WP_Widget_Wptuts_Twitter_Widget extends WP_Widget {

   var $w_arg;//A class variable to store an array of our widget settings and their default values

function __construct() {
    $widget_ops = array(
        'classname' => 'wptuts_widget_twitter',
        'description' => __('Displays a list of recent tweets','wptuts_twitter')
    );
    parent::__construct('WP_Widget_Wptuts_Twitter', __('Twitter','wptuts_twitter'), $widget_ops);

    //Sett widget's settings and default values
    $this->w_arg = array(
        'title'=> '',
        'screen_name'=> '',
        'count'=> '5',
        'published_when'=> '1',
    );
}

function widget( $args, $instance ) {
    extract($args);
    $title = apply_filters( 'widget_title', $instance['title'] );

    echo $before_widget;

    echo $before_title.esc_html($title).$after_title;

    echo $this->generate_tweet_list($instance);

    echo $after_widget;
}

function update( $new_instance=array(), $old_instance=array() ) {
    $validated = array();
    $validated['title'] = sanitize_text_field( $new_instance['title'] );
    $validated['screen_name']= preg_replace( '/[^A-Za-z0-9_]/', '',$new_instance['screen_name'] );
    $validated['count'] = absint( $new_instance['count'] );
    $validated['published_when'] = ( isset( $new_instance['published_when'] ) ? 1 : 0 );
    return $validated;
}

    function form( $instance=array() ) {
        //Merge $instance with defaults
        $instance = extract(wp_parse_args( (array) $instance, $this->w_arg ));
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title', 'wptuts_twitter'); ?>: </label>
            <input id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title);?>" />
        </p
>
        <p>
            <label for="<?php echo $this->get_field_id('screen_name'); ?>"><?php _e('Twitter username', 'wptuts_twitter'); ?>: </label>
            <input id="<?php echo $this->get_field_id('screen_name'); ?>" name="<?php echo $this->get_field_name('screen_name'); ?>" type="text" value="<?php echo esc_attr($screen_name);?>" />
        </p>

        <p>
            <label for="<?php echo $this->get_field_id('count'); ?>"><?php _e('Number of Tweets', 'wptuts_twitter'); ?>: </label>
            <input id="<?php echo $this->get_field_id('count'); ?>" name="<?php echo $this->get_field_name('count'); ?>" type="text" value="<?php echo intval($count);?>" />
        </p>

    <p>
        <label for="<?php echo $this->get_field_id('published_when'); ?>"><?php _e('Show when tweet was published', 'wptuts_twitter'); ?>: </label>
        <input  id="<?php echo $this->get_field_id('published_when'); ?>" name="<?php echo $this->get_field_name('published_when'); ?>" <?php checked($published_when,1); ?> type="checkbox" value="1" />
    </p>

        <?php
    }

function generate_tweet_list( $args=array() ){

     $args = shortcode_atts(array(
        'include_entities' => 'true',
        'include_rts'=>1,
        'screen_name' => '',
        'count'=>5,
        'published_when'=>1,
    ), $args);

    //Retrieve tweets
    $tweets = $this->get_tweets($args);

    $content = '<ul>';
    if ( is_wp_error($tweets) || !is_array($tweets) || count($tweets) ==0 ) {

        $content .= '<li>' . __( 'No Tweets Available', 'wptuts_twitter' ) . '</li>';

    } else {
        $count = 0;
        foreach ( $tweets as $tweet ) {

            $content .= '<li>';
                $content .= "<span class='tweet-content'>".$this->make_clickable($tweet)."</span></br>";

                if( $args['published_when'] ){
                    $content .= "<span class='time-meta'>";
                        $href = esc_url("http://twitter.com/{$tweet->user->screen_name}/statuses/{$tweet->id_str}");
                        $time_diff = human_time_diff( strtotime($tweet->created_at)).' ago';
                        $content .= "<a href={$href}>".$time_diff."</a>";
                    $content .= '</span>';
                }

            $content .= '</li>';

            if ( ++$count >= $args['count'] )
                break;
        }
    }
    $content .= '</ul>';
    //wp_enqueue_script('wptuts_twitter_script');
    //wp_enqueue_style('wptuts_twitter_style');

    return $content;
}

function make_clickable( $tweet ){
    $entities = $tweet->entities;
    $content = $tweet->text;

    //Make any links clickable
    if( !empty($entities->urls) ){
        foreach( $entities->urls as $url ){
            $content =str_ireplace($url->url,  '<a href="'.esc_url($url->expanded_url).'">'.$url->display_url.'</a>', $content);
        }
    }

    //Make any hashtags clickable
    if( !empty($entities->hashtags) ){
        foreach( $entities->hashtags as $hashtag ){
            $url = 'http://search.twitter.com/search?q=' . urlencode($hashtag->text);
            $content =str_ireplace('#'.$hashtag->text,  '<a href="'.esc_url($url).'">#'.$hashtag->text.'</a>', $content);
        }
    }

    //Make any users clickable
    if( !empty($entities->user_mentions) ){
        foreach( $entities->user_mentions as $user ){
            $url = 'http://twitter.com/'.urlencode($user->screen_name);
            $content =str_ireplace('@'.$user->screen_name,  '<a href="'.esc_url($url).'">@'.$user->screen_name.'</a>', $content);
        }
    }

    //Make any media urls clickable
    if( !empty($entities->media) ){
        foreach( $entities->media as $media ){
            $content =str_ireplace($media->url,  '<a href="'.esc_url($media->expanded_url).'">'.$media->display_url.'</a>', $content);
        }
    }

    return $content;
}

 function set_twitter_transient($key, $data, $expiration){
	//Time when transient expires
	$expire = time() + $expiration;
     set_transient( $key, array( $expire, $data ) );
 }

function get_tweets($args){
    //Build requirest url
    $args['screen_name'] = '@'.$args['screen_name'];
    $request_url = 'https://api.twitter.com/1/statuses/user_timeline.json';
    $request_url = add_query_arg($args,$request_url);

    //Generate key
    $key = 'wptt_'.md5($request_url);

    //expires every hour
    $expiration = 60*60; 

    $transient = get_transient( $key );

    if ( false === $transient ) {
        // Hard expiration
        $data = $this->retrieve_remote_tweets( $request_url );

        if( !is_wp_error($data) ){
            //Update transient
            $this->set_twitter_transient($key, $data, $expiration);
        }
        return $data;

    } else {
        // Soft expiration. $transient = array( expiration time, data)
        if ( $transient[0] !== 0 && $transient[0] <= time() ){

            //Expiration time passed, attempt to get new data
            $new_data = $this->retrieve_remote_tweets( $request_url  );

            if( !is_wp_error($new_data) ){
                //If successful return update transient and new data
                $this->set_twitter_transient($key, $new_data,  $expiration);
                $transient[1] = $new_data;
            }
        }
        return $transient[1];
    }
}

  function retrieve_remote_tweets($request_url){

    $raw_response = wp_remote_get( $request_url, array( 'timeout' => 1 ) );

    if ( is_wp_error( $raw_response ) )
        return $raw_response;

    $code = (int) wp_remote_retrieve_response_code($raw_response);
    $response = json_decode( wp_remote_retrieve_body($raw_response) );

    switch( $code ):
        case 200:
            return $response;

        case 304:
        case 400:
        case 401:
        case 403:
        case 404:
        case 406:
        case 420:
        case 500:
        case 502:
        case 503:
        case 504:
            return new WP_Error($code, $response->error); 

        default:
            return new WP_Error($code, __('Invalid response','wptuts_twitter'));
    endswitch;
}
 }

add_action( 'widgets_init', 'wptuts_register_widget');
function wptuts_register_widget(){
    register_widget('WP_Widget_Wptuts_Twitter_Widget');
}

   function wptuts_twitter_shortcode_cb( $atts ) {
        $args = shortcode_atts( array(
            'screen_name' => '',
            'count' => 5,
            'published_when' => 5,
            'include_rts' => 1,
               ), $atts );

        $tw = new WP_Widget_Wptuts_Twitter_Widget();
        return $tw->generate_tweet_list( $args );
    }
    add_shortcode( 'wptuts_twtter', 'wptuts_twitter_shortcode_cb' );
