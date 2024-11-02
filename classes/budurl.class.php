<?php

class BudURL extends BudURLService
{

    function __construct()
    {
        global $wpdb;

        wp_enqueue_script('zeroclipboard', plugins_url('/js/ZeroClipboard.js',dirname(__FILE__)) );

        if ( $this->hasCurl() ) {
            // Filter for our short link
            add_filter ( 'post_link', array( &$this, 'hook_post_link' ), 10, 3 );

            // Get budurl link button
            add_filter('get_sample_permalink_html', array( &$this, 'hook_get_sample_permalink_html' ), '',4);

            // set some tags
            add_action( 'wp_head', array( &$this, 'og_tags' ));
        }

        // Quick-Copy button
        add_filter('the_content', array( &$this, 'quickcopy_content_filter' ), 10, 1);
    }

    function og_tags()
    {
        $tags = array();

        $p = get_post();

        if ( $p->ID > 0 )
        {
            $tags['budurl:url'] = $this->get_budurl_link_for_post_id( $p->ID );
            $tags['og:url'] = $this->get_budurl_link_for_post_id( $p->ID );
        }

        if ( empty( $tags ) )
            return;

        $og_output = '';
        foreach ( (array) $tags as $tag_property => $tag_content )
        {
            // to accomodate multiple images
            $tag_content = (array) $tag_content;
            $tag_content = array_unique( $tag_content );

            foreach ( $tag_content as $tag_content_single )
            {
                if ( empty( $tag_content_single ) )
                    continue; // Don't ever output empty tags

                $og_tag = sprintf( '<meta property="%s" content="%s" />', esc_attr( $tag_property ), esc_attr( $tag_content_single ) );
                $og_output .= $og_tag . "\n";
            }
        }

        echo $og_output;
    }

    // get our shortcode
    function hook_post_link($permalink, $post, $leavename)
    {
        // check if the call was from a plugin
        $plugin_call = false;
        $bt = debug_backtrace();
        foreach ( $bt as $b )
        {
            if (strpos($b['file'],'wp-content/plugins') !== false)
            {
                $plugin_call = true;
            }

            // don't want to hook for our own plugin!
            if (strpos($b['file'],'budurl') !== false)
            {
                $plugin_call = false;
                break;
            }

            // custom for jetpack
            if (strpos($b['file'],'sharing-service') !== false && strpos($b['file'],'jetpack') !== false && $b['function'] == 'get_display' )
            {
                $plugin_call = false;
                break;
            }
            else if (strpos($b['file'],'sitemap') !== false) // Don't return custom links in sitemaps
            {
                $plugin_call = false;
                break;
            }
        }

        // return the budurl link
        if ( $plugin_call )
        {
            $link = $this->get_budurl_link($permalink, $post->ID);
            if ( strlen($link) > 0 )
            {
                return $link;
            }
        }

        // not a plugin, return a normal link
        return $permalink;
    }

    // Add our get bitly link button to the admin post view
    function hook_get_sample_permalink_html($return, $id, $new_title, $new_slug)
    {
        $p = get_post();

        if ( $p && isset($p->ID) )
        {
            $budurl_link = $this->get_budurl_link_for_post_id($p->ID);
            // var_dump($budurl_link);
            if ( strlen($budurl_link) > 0 )
            {
                return $return . "<span id='view-post-btn'><input id=\"budurl-link\" type=\"hidden\" value=\"$budurl_link\" /><a href='#' class='button button-small' onclick=\"prompt(&#39;URL:&#39;, jQuery('#budurl-link').val()); return false;\">View BudURL Link</a>\n";
            }
        }

        return $return;

    }

    function quickcopy_content_filter($content) {
        // Expects to run in the loop.
        if (!is_single()) return $content;
        if (budurl_settings('enable-quickcopy') != 'yes')
            return $content;
        $format = (budurl_settings('quickcopy-textbox') != 'yes') ? 'hidden' : 'text';
        // $format = 'button';

        $id = get_the_ID();
        $permalink = get_permalink();
        $link = $this->get_budurl_link_for_post_id($id);
        $flash_url = plugins_url('/js/ZeroClipboard.swf',dirname(__FILE__));
        $button = '<button id="budurl-copy-button" class="budurl-copy-button">Copy BudURL link</button>';
        $button .= '<input type="'.$format.'" id="budurl-shortlink" class="budurl-shortlink" value="'.$link.'" />';
        $button = '<div>'.$button.'</div>';
        $button .= '
            <script>
                copyToClipboard();
                jQuery("#budurl-shortlink").click(function() {
                    jQuery(this).select();
                });
                
                function copyToClipboard()
                {
                    //set path
                    ZeroClipboard.config( { swfPath: "'.$flash_url.'" } );
                    //create client
                    var client = new ZeroClipboard(jQuery(".budurl-copy-button"));
                    client.on( "ready", function(event) {
                        // console.log( "movie is loaded" );
                        client.on( "copy", function(event) {
                          event.clipboardData.setData("text/plain", event.target.nextSibling.value);
                          alert("BudURL has been copied into clipboard");
                        } );
                    });
                }
            </script>
        ';
        return $content.$button;
    }

    function get_budurl_link_for_post_id($post_id)
    {
        if ( $post_id )
        {
            $meta = get_post_meta($post_id);

            if ( isset($meta['budurl_url']) )
            {
                // $this->get_budurl_link($meta['budurl_url'][0], $post_id);
                return $meta['budurl_url'][0];
            }
        }

        // if we get here we need to create one
        $permalink = get_permalink($post_id);
        return $this->get_budurl_link($permalink, $post_id);
    }

    function get_budurl_link($permalink, $post_id)
    {
        $title = '';
        if ( $post_id )
        {
            $title = get_the_title($post_id);
            // echo $title;

            // snag our permalink for a comparison
            if ( strlen($permalink) == 0 )
            {
                $permalink = get_permalink($post_id);
            }

            $meta = get_post_meta($post_id);

            if ( isset($meta['budurl_url']) && isset($meta['budurl_long_url']) && $permalink == $meta['budurl_long_url'][0] )
            {
                return $meta['budurl_url'][0];
            }
        }

        if (!$title || $title == 'Auto Draft') 
            return NULL;

        // in theory this should never fail
        if ( ($data = $this->shorten($permalink, $title)) )
        {
            // verify it doesn't exist already
            $meta = get_post_meta($post_id);

            // update
            if ( isset($meta['budurl_url']) )
            {
                update_post_meta($post_id, 'budurl_url', $data['link']);
                update_post_meta($post_id, 'budurl_hash', $data['hash']);
                update_post_meta($post_id, 'budurl_long_url', $permalink);
            }
            // add
            else
            {
                add_post_meta($post_id, 'budurl_url', $data['link'], true);
                add_post_meta($post_id, 'budurl_hash', $data['hash'], true);
                add_post_meta($post_id, 'budurl_long_url', $permalink, true);
            }
            // var_dump($data);
            return $data['url'];
        }
        else
        {
            // TODO: throw error
            error_log("Unable to get a budurl link for permalink '$permalink' and post id '$post_id'!", 0);

            return NULL;
        }

        return NULL;
    }
}