<?php
/*

=head1 Photos Flickr

    Plugin Name:    Photos Flickr
    Plugin URI:     http://dannyman.toldme.com/photos-flickr/

    Author:         Danny Howard
    Author URI:     http://dannyman.toldme.com/

    Description:    Photos Flickr is a development version of a plugin
    that I am writing for WordPress blog software to display a user's
    Flickr photos within their blog.

    Version:        0.7.3

So . . . what have we here?

B<Pretty> functions are probably the most interesting -- they do the
heavy lifting of printing, say, an index page ...

B<General> functions are the "worker ants" which you may call
directly, or are in turn used by the B<pretty> functions to get things
done.  They come in two flavors of name: C<photos_photo_> and
C<photos_photoset_> depending on whether they are meant to service photo
pages or photoset pages.

B<Private> functions are documented for completeness, and for my own
sanity.  These may be changed at any time.  You are welcome to use
these, but be warned that you are voiding the warranty, and might get
spanked by a future version.

I<Don't use private functions unless you really know what you are
doing.>

B<Obsoleted> functions are functions that are no longer supported.  A
good many are just old names for functions that have been renamed.
These are sparingly documented for completeness, and to reassure you
that you're not as insane as your friends claim.

I<Don't use obsoleted functions unless you really really know what you
are doing!>

=cut

*/

	// require_once("photos-flickr-conf.php");
	require_once("phpFlickr/phpFlickr.php");

    // CONFIGURATION -- From photos-flickr-conf.php
	/* pretty obvious */
	$flickr_user    =   get_option('flickr_user');
	/* Get one at http://www.flickr.com/services/api/key.gne */
	$flickr_API_key =   'efc050ee72d23be84595fcf05995ebf6';

	/* Enable database-backed cache.  These defaults should "just work"
	* but if things get broken, try commenting out the following lines.
    *
    * TODO: Configurability-via-admin?
	* */
	$flickr_cache_db    = "mysql://".DB_USER.":".DB_PASSWORD."@".DB_HOST."/".DB_NAME;
	$flickr_cache_exp   = '600';
	$flickr_cache_table = $GLOBALS['table_prefix']."flickr_cache";

    // well, if we have to . . .
    // $debug = True;

	// set by photos_init()
	$f;                 // phpFlickr object -- API handle
    $person;            // findByUsername is supposed to return NSID, but ...
    $nsid;              // NSID (UID) on Flickr
    $photos_base_url;   // So we can get self-referential
    // CONTEXT
    $context    = 'index'; // index OR photo OR set OR tag ...
    $photo_id; $photoset_id; $tag;
    $photos_page       = 1; // conflicts with WordPress?
    $index_type = 'photo';
    // should we make pretty links?
    $pretty_links;

    // other globals
    $error_message;     // In case we want to flash the user / admin

    // fuck with rewrite .... hello world?
    // see last example at:
    // http://codex.wordpress.org/Function_Reference/WP_Rewrite
    // and, really:
    // http://boren.nu/downloads/feed_director.phps
    add_action('generate_rewrite_rules', 'photos_add_rewrite_rules');
    function photos_add_rewrite_rules( $wp_rewrite ) {
        global $post;
        $my_slug = $post->post_name;
        whine("my_slug: $my_slug");
        // NO IDEA
        $my_slug = 'photos';
        $photos_rewrite_rules = array (
            // photo=
            '('.$my_slug.')/photo/([0-9]{1,})/?$' => 'index.php?'.
              'pagename='.$wp_rewrite->preg_index(1).
              '&photo='.$wp_rewrite->preg_index(2),
            // photoset=
            '('.$my_slug.')/photoset/([0-9]{1,})/?$' => 'index.php?'.
              'pagename='.$wp_rewrite->preg_index(1).
              '&photoset='.$wp_rewrite->preg_index(2),
            // photoset= & photopage=
            '('.$my_slug.')/photoset/([0-9]{1,})/([0-9]{1,})/?$' => 'index.php?'.
              'pagename='.$wp_rewrite->preg_index(1).
              '&photoset='.$wp_rewrite->preg_index(2).
              '&photopage='.$wp_rewrite->preg_index(3),
            // photoset= & photo=
            '('.$my_slug.')/photoset/([0-9]{1,})/photo/([0-9]{1,})/?$' => 'index.php?'.
              'pagename='.$wp_rewrite->preg_index(1).
              '&photoset='.$wp_rewrite->preg_index(2).
              '&photo='.$wp_rewrite->preg_index(3),
            // phototag= & photopage=
            '('.$my_slug.')/tag/(.+)/([0-9]{1,})/?$' => 'index.php?'.
              'pagename='.$wp_rewrite->preg_index(1).
              '&phototag='.$wp_rewrite->preg_index(2).
              '&photopage='.$wp_rewrite->preg_index(3),
            // phototag=
            '('.$my_slug.')/tag/(.+)/?$' => 'index.php?'.
              'pagename='.$wp_rewrite->preg_index(1).
              '&phototag='.$wp_rewrite->preg_index(2),
            // index=
            '('.$my_slug.')/photosets/?$' => 'index.php?'.
              'pagename='.$wp_rewrite->preg_index(1).
              '&index=photoset',
        );
//          '('.$my_slug.')/photo/([0-9]{1,})' => 'index.php',
//          'hello-world.html' => 'index.php'
//           => 'index.php?pagename='.$wp_rewrite->preg_index(1).'?photo='.$wp_rewrite->preg_index(2)
        $wp_rewrite->rules = $wp_rewrite->rules + $photos_rewrite_rules;
    }
    // and, also:
    // http://codex.wordpress.org/Custom_Queries#Implementing_Custom_Queries
    add_action('init', 'photos_flush_rewrite_rules');
    function photos_flush_rewrite_rules() {
       global $wp_rewrite;
       $wp_rewrite->flush_rules();
    }
    // but then, we must also register our queryvars with WordPress?
    add_filter('query_vars', 'photos_queryvars');
    function photos_queryvars( $qvars ) {
        $qvars[] = 'photo';
        $qvars[] = 'photoset';
        $qvars[] = 'index';
        $qvars[] = 'phototag';
        $qvars[] = 'photopage';
        return $qvars;
    }

/*

=head2 PRETTY FUNCTIONS

Top-level functions that implement serious magic and generate copious
HTML.

=cut

*/

/*

=head3 photos_photo_index()

Print a reasonable photo index.

Consults the context in which the page was called, and will print only
photos from a photoset or tag, if appropriate.  Otherwise, you get the
whole photostream.

B<The semantics of this function changed substantially in 0.8!>

 Arguments:
    $num_per_page       = number of photos to print per page
    $num_per_row        = number of photos to print between $row_sep
    $page               = what page number we are printing
    
    $img_size           = what size image to print
    $img_before         = what to print before IMG
    $img_after          = what to print after IMG

    $row_sep            = what to print between rows
    
    $photo_before       = what to print before each photo
    $photo_after        = what to print after each photo
    
    $print_title        = print title?
    $title_before       = what to print before title
    $title_after        = what to print after title
    
    $print_description  = print description?
    $description_before = what to print before description
    $description_after  = what to print after description

You'll end up with a page of photos, and each photo looks something
like:

 $photo_before
  $title_before         PHOTO TITLE         $title_after
  $img_before           PHOTO IMG TAG       $img_after
  $description_before   PHOTO DESCRIPTION   $description_after
 $photo_after

For $print_description, if you pass an integer, the description will
truncated after $print_description characters.

=cut

*/
	function photos_photo_index(
      $num_per_page = 25, 
      $num_per_row = 5, 
      $page = NULL,
      $img_size = 'square', $img_before = '', $img_after = '',
      $row_sep = '<br />',
      $photo_before = '', $photo_after = '',
      $print_title = false, $title_before = '', $title_after = '',
      $print_description = false, $description_before = '',
      $description_after = ''
    ) {
        global $context, $tag, $photoset_id;
        global $f, $nsid, $photos_base_url, $pretty_links;
        global $debug;

        whine("page: ". $page);
        if(! $page ) { $page = $GLOBALS['photos_page']; }
        whine("page: ". $page);

		whine("context: ", $context);

        if( $context == "photoset" ) {
        // http://www.flickr.com/services/api/flickr.photosets.getPhotos.html
            $photos = $f->photosets_getPhotos($photoset_id, NULL, NULL,
                $num_per_page, $page);
        } elseif( $context == "tag" ) {
        // http://www.flickr.com/services/api/flickr.photos.search.html
			$photos = $f->photos_search(array("user_id"=>$nsid,
				"tags"=>$tag, "per_page"=>$num_per_page,
				"page"=>$page));
		} else {
        // http://www.flickr.com/services/api/flickr.people.getPublicPhotos.html
			$photos = $f->people_getPublicPhotos($nsid, NULL,
				$num_per_page, $page);
		}

        // does not appear to do anything ...
        if( $context == "photoset" ) {
            if( $pretty_links ) {
                $my_photos_base_url = $photos_base_url."photoset/$photoset_id/";
            } else {
                $my_photos_base_url = $photos_base_url."photoset=$photoset_id&";
            }
        }
        else {
            $my_photos_base_url = $photos_base_url;
        }

		foreach ((array)$photos['photo'] as $photo) {
            print $photo_before;
            if( $print_title ) {
                $my_title = strip_tags($photo['title']);
                if( is_numeric($print_title) &&
                    (strlen($my_title) > $print_title) ) {
                    $my_title = substr_replace($my_title,
                      "&mdash;", $print_title);
                }
                print $title_before.$my_title.$title_after;
            }
            if( $pretty_links ) {
    			print $img_before.'<a href="'.$my_photos_base_url.
                  'photo/'.$photo['id'].'">'.
                  photos_private_img($photo, $img_size).'</a>'.$img_after;
            } else {
    			print $img_before.'<a href="'.$my_photos_base_url.
                  'photo='.$photo['id'].'">'.
                  photos_private_img($photo, $img_size).'</a>'.$img_after;
            }
            if( $print_description ) {
                $photo = $f->photos_getInfo($photo['id']);
                $my_description = strip_tags($photo['description']);
                if( is_numeric($print_description) &&
                    (strlen($my_description) > $print_description) ) {
                    $my_description = substr_replace($my_description,
                      "&mdash;", $print_description);
                }
                print $description_before.$my_description.$description_after;
            }
            print $photo_after."\n";
			$i++;
			if( is_numeric($num_per_row) && $i%$num_per_row == 0 ) {
				print $row_sep."\n";
			}
		}
	}

/*

=head3 photos_photo_pageindex()

Prints a "pagination" doohickey.

 Arguments:
    $context            = number of adjoining pages to print links to
    $item_sep           = what to put between page links
    $dotdotdot          = what to put between page link sets
    $page               = what page are we on, anyway?

A call like this:

 <?php photos_photo_pageindex(1, ', ', ' . . . ', 5); ?>

Might produce something like this:

1, 2 . . . 4, B<5>, 6 . . . 68, 69

=cut

*/

	function photos_photo_pageindex( $context = 2, $num_per_page = 25,
		$item_sep = ', ', $dotdotdot = " . . . ",$page = NULL ) {

        global $tag, $photoset_id;
        // This conflict was fun to puzzle over!  =D
        $global_context = $GLOBALS['context'];
        if( $debug ) { echo "global_context: $global_context"; }
		// Bring in our flickr object and NSID
        global $f, $nsid, $photos_base_url, $pretty_links;
        global $debug;
		// $f = $GLOBALS['f'];
		// $nsid = $GLOBALS['nsid'];
        // $photos_base_url = $GLOBALS['photos_base_url'];
		// IF no page is passed, check $_REQUEST, else set to 1
		if( $page == NULL ) { $page = $GLOBALS['photos_page']; }
		//	if( $_REQUEST['page'] ) { $page = $_REQUEST['page']; }
		//	else                    { $page = 1; }
            

		// Count the number of pages ...
        if( $global_context == "photoset" ) {
            $photos = $f->photosets_getPhotos($photoset_id, NULL, NULL,
                $num_per_page, $page);
        } elseif( $global_context == "tag" ) {
			$photos = $f->photos_search(array("user_id"=>$nsid,
				"tags"=>$tag, "per_page"=>$num_per_page,
				"page"=>$page));
		} else {
			$photos = $f->people_getPublicPhotos($nsid, NULL,
				$num_per_page, $page);
		}
		$pagecount = $photos['pages'];
        whine("pagecount: $pagecount");

        // emit means "print a comma"
		$n = 1; $emit = 0; $printme = '';
		while( $n <= $pagecount ) {
            // DO print numeric links
			if( 
                // 1, 2, 3 ...
				( 1 <= $n && $n <= (1+$context) )                    ||
				// ... 7, 8, [9], 10, 11 ...
				( ($page-$context) <= $n && $n <= ($page+$context) ) ||
				// ... 98, 99, 100
				( ($pagecount-$context) <= $n && $n <= $pagecount ) ) {
                // print a comma?
       			if( $emit != 0 ) { print $item_sep; $emit = 0; }
    			if( $n == $page ) { // CURRENT page number
    				print "<b>$n</b>";
    			} else {            // LINK page number
                    // yes this has gotten extremely ugly ...
                    if( $pretty_links ) {
                        if( $global_context == "photoset" ) {
        					print "<a href=\"".$photos_base_url.
        						"photoset/$photoset_id/$n\">$n</a>";
                        } elseif ( $global_context == "tag" ) {
        					print "<a href=\"".$photos_base_url.
        						"tag/$tag/$n\">$n</a>";
        				} else {
        					print "<a href=\"".$photos_base_url.
        						"$n\">$n</a>";
        				}
                    } else { // ugly links
                        if( $global_context == "photoset" ) {
        					print "<a href=\"".$photos_base_url.
        						"photoset=$photoset_id&photopage=$n\">$n</a>";
                        } elseif ( $global_context == "tag" ) {
        					print "<a href=\"".$photos_base_url.
        						"phototag=$tag&photopage=$n\">$n</a>";
        				} else {
        					print "<a href=\"".$photos_base_url.
        						"photopage=$n\">$n</a>";
        				}
                    }
    			}
    			$emit++;
            // DO NOT print numeric links
        }
            else if( $emit != 0 ) { print $dotdotdot; $emit = 0; }
    		$n++;
        }
    }

/*

=head3 photos_photoset_index()

Print a reasonable photoset index.

 Arguments:
    $num_per_row    	= number of sets to print between $row_sep
    $row_sep        	= what to print between rows
    
    $print_thumb    	= print thumbnail?  Unless NULL, what kind?
    $print_title    	= print title?
    $print_count    	= print count?
    
    $photoset_before 	= what to print before photoset
    $photoset_after 	= what to print after photoset
    
    $thumb_before   	= what to print before thumbnail
    $thumb_after    	= what to print after thumbnail
    
    $title_before   	= what to print before title
    $title_after    	= what to print after title
    
    $count_before   	= what to print before count
    $count_after    	= what to print after count

You'll end up with a page of photosets, and each photoset looks
something like:

 $photoset_before
  $thumb_before PHOTOSET IMG THUMBNAIL  $thumb_after
  $title_before PHOTOSET TITLE          $title_after
  $count_before PHOTOSET COUNT          $count_after
 $photoset_after

=cut

*/

	function photos_photoset_index(
      $num_per_row = 6, $row_sep = '<br />',
      $print_thumb='square', $print_title = true, $print_count = NULL,
      $photoset_before = '<div style="float: left; width: 85px; height: 170px;">',
      $photoset_after='</div>',
      $thumb_before = '', $thumb_after= '',
      $title_before = '<br />', $title_after='',
      $count_before = '<br />', $count_after = ' photos'
    ) {
        global $f, $nsid;
        $my_photosets = $f->photosets_getList($nsid);
        foreach( (array)$my_photosets['photoset'] as $my_photoset ) {
            // vars
            $my_count = $my_photoset['photos'];
            $my_id = $my_photoset['id'];
            $my_primary = $my_photoset['primary'];
            $my_title = $my_photoset['title'];
            // divide-by-zero
            if(! $num_per_row ) { $num_per_row = 1; }
            // printy
            print $photoset_before;
            if( $print_thumb ) {
                // tricky: make a "photo" that we can pass to
                // PHPFlickr's buildPhotoURL ...
                $my_thumb = $my_photoset;
                // "primary" references the photoset's "primary image"
                $my_thumb['id'] = $my_photoset['primary'];
                print "<!-- id: $my_id primary: $my_primary ".
                  "print_thumb: $print_thumb -->";
                print $thumb_before.'<a href="';
                photos_photoset_href($my_id);
                print '">'.photos_private_img($my_thumb, $print_thumb).
                  '</a>'.$thumb_after;
            }
            if( $print_title ) {
                print "$title_before".'<a href="';
                photos_photoset_href($my_id);
                print '">'."$my_title</a>$title_after";
            }
            if( $print_count ) {
                print "$count_before$my_count$count_after";
            }
            print $photoset_after."\n";
			$i++;
			if( $i%$num_per_row == 0 ) {
				print $row_sep."\n";
			}
        }
    }

/*

=head2 GENERAL FUNCTIONS

=cut

*/

/*

=head3 photos_init()

 MANDATORY call once before you call other stuff:
 * Connects to Flickr, setting up phpFlickr handle ($f)
 * Connects to cache database
 * Sets up $person and $nsid for our convenience
 * Sets up $photos_base_url appropriately

TODO: If we are hooking in to WordPress' init() then this should check
to see if we're actually doing any photos before it goes pinging Flickr
and setting up a database connection, huh?

=cut

*/
	function photos_init() {
        global $f, $person, $nsid, $photos_base_url;
        global $flickr_user, $flickr_API_key;
        global $flickr_cache_db, $flickr_cache_exp, $flickr_cache_table;
        global $context, $photo_id, $photoset_id, $tag, $photos_page, $index_type;
        global $pretty_links;
        global $init_done; // Careful not to overdo it.
        // WordPress
        global $wp_query, $wp_rewrite;
        // Connects to Flickr, setting up phpFlickr handle ($f)
		photos_init_phpFlickr();
		// Sets up $person and $nsid for our convenience
		// $person = $f->people_findByUsername($GLOBALS['flickr_user']);
		$person = get_flickr_user($f, $flickr_user);
        // WTF: findByUsername used to return NSID directly.
		$nsid = $person['id'];
        // Establish context: index, set, tag, or none
        // Populate from $_REQUEST ... this may need to be broken into
        // its own function.
        //if( $_REQUEST['photo'] ) {
        //    $photo_id = $_REQUEST['photo'];
        //}
        //else { // photo or index of what?  (photos, by default)
        //    if( $_REQUEST['index']  ) {
        //        $index_type = $_REQUEST['index'];
        //    }
        //    else {
        //        $index_type = 'photo';
        //    }
        //}
        //if( $_REQUEST['photoset'] ) {
        //    $context = 'photoset';
        //    $photoset_id = $_REQUEST['photoset'];
        //}
        //if( $_REQUEST['tag'] ) {
        //    $context = 'tag';
        //    $tag = $_REQUEST['tag'];
        //}
		//if( $_REQUEST['page'] ) {
        //    $page = $_REQUEST['page'];
        //}
		//whine("query_vars: <pre>".print_r($wp_query->query_vars)."</pre>");
        if( isset($wp_query->query_vars['photo']) ) {
            $photo_id = $wp_query->query_vars['photo'];
        }
        else { // photo or index of what?  (photos, by default)
            if( isset($wp_query->query_vars['index'])  ) {
                $index_type = $wp_query->query_vars['index'];
            }
            else {
                $index_type = 'photo';
            }
        }
		whine("context: $context");
        if( isset($wp_query->query_vars['photoset']) ) {
            $context = 'photoset';
            $photoset_id = $wp_query->query_vars['photoset'];
        }
        if( isset($wp_query->query_vars['phototag']) ) {
            $context = 'tag';
            $tag = $wp_query->query_vars['phototag'];
        }
		whine("context: $context");
        whine("query_vars->photopage: ".$wp_query->query_vars['photopage']);
		if( isset($wp_query->query_vars['photopage']) ) {
            whine("setting photos_page = ".$wp_query->query_vars['photopage']);
            $photos_page = $wp_query->query_vars['photopage'];
            whine("set photos_page = ".$photos_page);
        }
        whine("page: ".$photos_page);
        // Pretty Permalinks?
        if( $wp_rewrite->using_permalinks() ) {
            $pretty_links = false;
        }
        // Sets up $photos_base_url appropriately
        // TODO: This should be configurable?
        $photos_base_url = get_permalink();
        // /?page_id=3 -> /?page_id=3&  . . . /photos/ -> /photos/?
        if( $pretty_links != true ) { $photos_base_url .= '&'; }
		else if( strstr($photos_base_url, '?') ) { $photos_base_url .= '&'; }
        else { $photos_base_url .= '?'; }
        whine("photos_base_url: $photos_base_url");
        if( $GLOBALS['debug'] && $pretty_links == true ) {
            global $wp_rewrite;
            print("<pre>\n");
            foreach( $wp_rewrite->rules as $key => $value ) {
                print "$key =&gt; $value\n";
            }
            print ("</pre>\n");
        }
//        whine("<pre>rewrite_rules:\n".$wp_rewrite->mod_rewrite_rules()."</pre>");
	}
    // wp_head is far enough to build $photos_base_url
    // BUT this is somewhat silly.
    // add_action('wp_head', 'photos_init');

/*

=head3 photos_photo_index_href()

Print a link to a photo index page.

=cut

*/
    function photos_photo_index_href() {
//        global $photos_base_url;
//        print $photos_base_url;
        print get_permalink();
    }

/*

=head3 photos_photoset_index_href()

Print a link to a photoset index page.

=cut

*/
    function photos_photoset_index_href() {
        global $photos_base_url, $pretty_links;
        if( $pretty_links ) {
            print $photos_base_url."photosets/";
        } else {
            print $photos_base_url."index=photoset";
        }
    }

/*

=head3 photos_is_photo()

Are we a page for one photo, or some nav index?

Returns Flickr photo ID of current photo.

Returns false if there is no photo.

=cut

*/

	function photos_is_photo() {
        global $photo_id;
        // I wonder if this isn't the same as: return $photo_id;
        if( $photo_id ) { return $photo_id; } else { return false; }
	}

/*

=head3 photos_is_index()

Are we an index page?  If so, what flavor?

Unless we are an photos_is_photo() will return "index" or "photoset"

Returns false if we are not an index.

If we are not a photo or an index, we might be a photoset page, or a tag
page ...

=cut

*/

	function photos_is_index() {
        global $index_type;
        if( photos_is_photo() == false && $index_type ) {
            return $index_type;
        }
        else {
            return false;
        }
	}

/*

=head3 photos_is_photoset()

Returns ID of current photoset, else false.

=cut

*/

    function photos_is_photoset() {
        global $context, $photoset_id;
        if( $context == "photoset" ) {
            return $photoset_id;
        } else {
            return false;
        }
    }

/*

=head3 photos_is_tag()

Returns value of current tag, else false.

=cut

*/

    function photos_is_tag() {
        global $context, $tag;
        if( $context == "tag" ) {
            return $tag;
        } else {
            return false;
        }
    }

/*

=head2 GENERAL FUNCTIONS: photos_photo_

 TODO: photos_photo_list();
 photos_photo_description();
 photos_photo_href();
 photos_photo_title();
 photos_photo_img();
  
 photos_photo_photoset_list();
 photos_photo_tag_list();

You can pass a photo ID to any of these functions.  Otherwise, they'll
default to the current photo.

=cut

*/

/*

=head3 photos_photo_description()

Print photo description.

=cut

*/

	function photos_photo_description( $photo_id = NULL ) {
		if( $photo_id == NULL ) { $photo_id = $GLOBALS['photo_id']; }
		// Bring in our flickr object
		$f = $GLOBALS['f'];

		$photo = $f->photos_getInfo($photo_id);
		$description = str_replace("\n", '<br />', $photo[description]);
		print $description;
	}

/*

=head3 photos_photo_href() 

Print Photos Flickr URL of photo page for $photo_id.

=cut

*/

    function photos_photo_href( $photo_id = NULL ) {
        if( $photo_id == NULL ) { $photo_id = $GLOBALS['photo_id']; }
		global $photos_base_url, $pretty_links;
        if( $pretty_links ) {
            print $photos_base_url."photo/".$photo_id;
        } else {
            print $photos_base_url."photo=".$photo_id;
        }
    }

/*

=head3 photos_photo_title()

Print photo description.

=cut

*/

	function photos_photo_title( $photo_id = NULL ) {
		if( $photo_id == NULL ) { $photo_id = $GLOBALS['photo_id']; }
		// Bring in our flickr object
		$f = $GLOBALS['f'];

		$photo = $f->photos_getInfo($photo_id);
		print $photo[title];
	}


/*

=head3 photos_photo_url()

Print a Flickr IMG link to photo.

=cut

*/
	function photos_photo_url( $size='medium', $photo_id = NULL ) {
		if( $photo_id == NULL ) { $photo_id = $GLOBALS['photo_id']; }
		// Bring in our flickr object
		$f = $GLOBALS['f'];

		$photo = $f->photos_getInfo($photo_id);
		print $f->buildPhotoURL($photo, $size);
	}

/*

=head3 photos_photo_photosets() 

Print linked photosets for a photo.

=cut

*/

    function photos_photo_photosets(
      $photoset_before = '', $photoset_after = ' ', $photo_id = NULL ) {
		if( $photo_id == NULL ) { $photo_id = $GLOBALS['photo_id']; }
		// Bring in our flickr object
		global $f, $photos_base_url;
        // Get $photo ...
		$photo = $f->photos_getAllContexts($photo_id);

            // Get a list of sets for this photo
    		$my_contexts = $f->photos_getAllContexts($photo_id);
            // $my_context == array of <set /> tags with keys for id, title
            foreach( $my_contexts['set'] as $my_context ) {
                $my_return[] = $my_context['id'];
            }

		if( $photo['set'] ) {
            $length = count($photo['set']);
			foreach( $photo['set'] as $set ) { $i++;
			    print "$photoset_before<a href=\"";
                photos_photoset_href($set['id']);
                print "\">$set[title]</a>";
                if( $i < $length ) print $photoset_after;
			}
		}
	}

/*

=head3 photos_photo_tags()

Print a linked list of tags for a photo.

=cut

*/

	function photos_photo_tags( $tag_before = '', $tag_after = ' ',
		$photo_id = NULL ) {
		if( $photo_id == NULL ) { $photo_id = $GLOBALS['photo_id']; }
		// Bring in our flickr object
		$f = $GLOBALS['f'];
        // So we can build our HREF action!
        $photos_base_url = $GLOBALS['photos_base_url'];
        global $pretty_links;

        // Get $photo ...
		$photo = $f->photos_getInfo($photo_id);

		if( $photo['tags'] ) {
            $length = count($photo['tags']['tag']);
			foreach( $photo['tags']['tag'] as $tag ) { $i++;
                    if( $pretty_links ) {
    			    print "$tag_before<a href=\"".$photos_base_url.
                        "tag/$tag[_content]\">$tag[raw]</a>";
                    } else {
    			    print "$tag_before<a href=\"".$photos_base_url.
                        "phototag=$tag[_content]\">$tag[raw]</a>";
                    }
                if( $i < $length ) print $tag_after;
			}
		}
	}

/*

=head3 photos_photo_photoset_list()

Returns a PHP list of photosets associated with photo.

Returns False if no photosets.  (So you could use it as a boolean
check.)

=cut

*/

    function photos_photo_photoset_list( $photo_id = NULL ) {
		if( $photo_id == NULL ) { $photo_id = $GLOBALS['photo_id']; }
        return photos_photoset_list($photo_id);
    }

/*

=head3 photos_photo_tag_list()

Returns a PHP list of tags associated with photo.

Returns False if no tags.  (So you could use it as a boolean check.)

=cut

*/

	function photos_photo_tag_list( $photo_id = NULL ) {
		if( $photo_id == NULL ) { $photo_id = $GLOBALS['photo_id']; }
        // Get $photo ...
		global $f;
		$photo = $f->photos_getInfo($photo_id);

		if( $photo['tags'] ) {
            // $my_tags = $photo['tags']['tag'];
			// foreach( $my_tags as &$my_tag ) {
            //     $my_tag = $my_tag['raw'];
            // }
            // return $my_tags;
			return $photo['tags']['tag'];
		} else {
            return(False);
        }
	}

/*

=head3 photos_photo_page_url()

Print a link to Flickr photo page

=cut

*/

	function photos_photo_page_url( $photo_id = NULL ) {
        global $person;
		if( $photo_id == NULL ) { $photo_id = $GLOBALS['photo_id']; }
		print "http://www.flickr.com/photos/".
			$person['username']."/".$photo_id."/";
	}

/*

=head3 photos_photo_date_taken()

Print the date photo was taken.

Accepts $date_format as second argument -- a format string for PHP's
date() function.  If this is not supplied, the WordPress B<date_format>
option will be checked.

See: http://www.php.net/date

Example:

 photos_photo_date_taken(NULL, 'Y-m-d, H\hi');

(The first argument is the photo ID.  NULL means to default to current
photo.)

Would print something like:

2007-06-25, 15h58

=cut

*/

    function photos_photo_date_taken( $photo_id = NULL, 
      $date_format = NULL ) {
        if( $photo_id == NULL ) { $photo_id = $GLOBALS['photo_id']; }
        // Hey, we set this default somewhere in the WP admin, neh?
        if( $date_format == NULL ) { $date_format = get_option('date_format'); }
        // get busy -- get $f!
        global $f;
        $photo = $f->photos_getInfo($photo_id);
        if( $date_format == NULL ) { // Why bother?
            print $photo[dates][taken];
        } else {                    // If you insist ...
            /* We get something like:
                2007-05-22 01:31:15
                0123456789012345678
                0         1
                That's why the stdlib invented substr():
                http://us.php.net/manual/en/function.substr.php
                We need to feed the mktime():
                hour, minute, second, month, day, year
                http://us.php.net/manual/en/function.mktime.php */
            $my_dates_taken = $photo[dates][taken];
            $my_hour = substr($my_dates_taken, 11, 2);
            $my_min = substr($my_dates_taken, 14, 2);
            $my_sec = substr($my_dates_taken, 17, 2);
            $my_mon = substr($my_dates_taken, 5, 2);
            $my_day = substr($my_dates_taken, 8, 2);
            $my_year = substr($my_dates_taken, 0, 4);
            $my_timestamp = mktime($my_hour, $my_min, $my_sec,
              $my_mon, $my_day, $my_year);
            print date($date_format, $my_timestamp);
        }
    }

/*

=head3 photos_photo_next_img()

Print IMG link for next photo in stream.

Accepts Flickr image size type as first argument: defaults to "square"

=head3 photos_photo_prev_img()

Print IMG link for previous photo in stream.

Accepts Flickr image size type as first argument: defaults to "square"

=head3 photos_photo_next_href()

Print link to Photos Flickr page for next photo in stream.

=cut

=head3 photos_photo_prev_href()

Print link to Photos Flickr page for previous photo in stream.

=cut

=head3 photos_photo_next_title()

Print title of next photo in stream.

=cut

=head3 photos_photo_prev_title()

Print title of previous photo in stream.

=cut

*/

	function photos_photo_next_img( $thumb_type = 'square',
      $photo_id = NULL ) {
        print photos_private_getContext("photo", "next", "img",
          $photo_id, NULL, $thumb_type);
	}
	function photos_photo_prev_img( $thumb_type = 'square',
      $photo_id = NULL ) {
        print photos_private_getContext("photo", "prev", "img",
          $photo_id, NULL, $thumb_type);
	}
	function photos_photo_next_href( $photo_id = NULL ) {
        print photos_private_getContext("photo", "next", "href", $photo_id);
	}
	function photos_photo_prev_href( $photo_id = NULL ) {
        print photos_private_getContext("photo", "prev", "href", $photo_id);
	}
	function photos_photo_next_title( $photo_id = NULL ) {
        print photos_private_getContext("photo", "next", "title", $photo_id);
	}
	function photos_photo_prev_title( $photo_id = NULL ) {
        print photos_private_getContext("photo", "prev", "title", $photo_id);
	}

/*

=head2 GENERAL FUNCTIONS: photos_photosets_

 photos_photoset_list();
 photos_photoset_description();
 photos_photoset_href();
 photos_photoset_title();
 photos_photoset_img();

These functions generally accept at least one argument: $photoset_id,
which is the ID of a Flickr photoset.

=cut

*/

/*

=head3 photos_photoset_list() 

Return a list of photosets associated with $photo_id.

Defaults to returning a list of all sets for user.

Returns False if no photosets.

=cut

*/

	function photos_photoset_list( $photo_id = NULL ) {
        global $f, $nsid;

        if( $photo_id ) {
            // Get a list of sets for this photo
    		$my_contexts = $f->photos_getAllContexts($photo_id);
            // $my_context == array of <set /> tags with keys for id, title
            if( $my_contexts['set'] ) {
                foreach( $my_contexts['set'] as $my_context ) {
                    $my_return[] = $my_context['id'];
                }
            }
        } else {
            // Get a list of sets for this user
            $my_photosets = $f->photosets_getList($nsid);
            // $my_photosets == array of <photoset /> tags with keys for id,
            // title, description
            if( $my_photosets['photoset'] ) {
                foreach( $my_photosets['photoset'] as $my_photoset ) {
                    $my_return[] = $my_photoset['id'];
                }
            }
        }

        if( $my_return ) { return $my_return; }
        // else
        return False;
	}

/*

=head3 photos_photoset_description() 

Print description of $photoset_id.

=cut

*/

    function photos_photoset_description( $photoset_id = NULL ) {
        if( $photoset_id == NULL ) { $photoset_id = $GLOBALS['photoset_id']; }
        global $f;
		$set = $f->photosets_getInfo($photoset_id);
		$description = str_replace("\n", '<br />', $set[description]);
		print $description;
    }

/*

=head3 photos_photoset_href() 

Print Photos Flickr URL of photoset page for $photoset_id.

=cut

*/

    function photos_photoset_href( $photoset_id = NULL ) {
        if( $photoset_id == NULL ) { $photoset_id = $GLOBALS['photoset_id']; }
		global $photos_base_url, $pretty_links;
        if( $pretty_links ) {
            print $photos_base_url."photoset/".$photoset_id;
        } else {
            print $photos_base_url."photoset=".$photoset_id;
        }
    }

/*

=head3 photos_photoset_title() 

Print Title of $photoset_id.

=cut

*/

    function photos_photoset_title( $photoset_id = NULL ) {
        if( $photoset_id == NULL ) { $photoset_id = $GLOBALS['photoset_id']; }
        global $f;
		$set = $f->photosets_getInfo($photoset_id);
		print $set[title];
    }

/*

=head3 photos_photoset_img() 

Print primary image URL of $photoset_id.

=cut

*/

    function photos_photoset_img( $photoset_id = NULL, $thumb_type = 'square' ) {
        if( $photoset_id == NULL ) { $photoset_id = $GLOBALS['photoset_id']; }
        global $f;
		$set = $f->photosets_getInfo($photoset_id);
        $my_primary_id = $set['primary'];
        // Now we need to getInfo($my_primary_id)
		$my_primary_photo = $f->photos_getInfo($my_primary_id);
        //print "<img class=\"photosThumb\" id=\"photosId_".
		//		  $my_primary_id."\" src=\"".
		//		  $f->buildPhotoURL($my_primary_photo, $thumb_type).
		//		  // "\" title=\"".$my_primary_photo['title'].
        //        "\" />";
        print photos_private_img($my_primary_photo, $thumb_type);
    }

/*

=head3 photos_photoset_next_img()

Print IMG link for next photo in photoset.

 Arguments:
    $photoset_id    = Flickr ID of photoset
    $thumb_type     = Flickr image size for "thumbnail"
    $photo_id       = Flickr ID of photo

$photoset_id and $photo_id default to current values.

Accepts Flickr image size type as second argument: defaults to "square"

=head3 photos_photoset_prev_img()

Print IMG link for previous photo in photoset.

 Arguments:
    $photoset_id    = Flickr ID of photoset
    $thumb_type     = Flickr image size for "thumbnail"
    $photo_id       = Flickr ID of photo

$photoset_id and $photo_id default to current values.

Accepts Flickr image size type as second argument: defaults to "square"

=head3 photos_photoset_next_href()

Print link to Photos Flickr page for next photo in photoset.

 Arguments:
    $photoset_id    = Flickr ID of photoset
    $photo_id       = Flickr ID of photo

$photoset_id and $photo_id default to current values.

=cut

=head3 photos_photoset_prev_href()

Print link to Photos Flickr page for previous photo in photoset.

 Arguments:
    $photoset_id    = Flickr ID of photoset
    $photo_id       = Flickr ID of photo

$photoset_id and $photo_id default to current values.

=cut

=head3 photos_photoset_next_title()

Print title of next photo in photoset.

 Arguments:
    $photoset_id    = Flickr ID of photoset
    $photo_id       = Flickr ID of photo

$photoset_id and $photo_id default to current values.

=cut

=head3 photos_photoset_prev_title()

Print title of previous photo in photoset.

 Arguments:
    $photoset_id    = Flickr ID of photoset
    $photo_id       = Flickr ID of photo

$photoset_id and $photo_id default to current values.

=cut

*/

	// PUBLIC: Print next photoset thumbnail
	function photos_photoset_next_img( $photoset_id = NULL, $thumb_type = 'square',
      $photo_id = NULL ) {
        print photos_private_getContext("photoset", "next", "img",
          $photo_id, $photoset_id, $thumb_type);
	}

	// PUBLIC: Print prev photoset thumbnail
	function photos_photoset_prev_img( $photoset_id = NULL, $thumb_type = 'square',
      $photo_id = NULL ) {
        print photos_private_getContext("photoset", "prev", "img",
          $photo_id, $photoset_id, $thumb_type);
	}

	// PUBLIC: Print next photoset href
	function photos_photoset_next_href( $photoset_id = NULL, $photo_id = NULL ) {
        print photos_private_getContext("photoset", "next", "href",
          $photo_id, $photoset_id);
	}

	// PUBLIC: Print prev photoset href
	function photos_photoset_prev_href( $photoset_id = NULL, $photo_id = NULL ) {
        print photos_private_getContext("photoset", "prev", "href",
          $photo_id, $photoset_id);
	}

	// PUBLIC: Print next photoset title
	function photos_photoset_next_title( $photoset_id = NULL, $photo_id = NULL ) {
        print photos_private_getContext("photoset", "next", "title",
          $photo_id, $photoset_id);
	}

	// PUBLIC: Print prev photoset title
	function photos_photoset_prev_title( $photoset_id = NULL, $photo_id = NULL ) {
        print photos_private_getContext("photoset", "prev", "title",
          $photo_id, $photoset_id);
	}

/*

=head2 PRIVATE FUNCTIONS

=cut

*/

/*

=head3 PRIVATE: photos_init_phpFlickr()

Connects to Flickr, setting up $f

=cut

*/
    function photos_init_phpFlickr() {
        global $f, $flickr_API_key;
        global $flickr_cache_db, $flickr_cache_exp, $flickr_cache_table;
        if( $f ) { return; } // Why reinvent the $f?
        $f = new phpFlickr($flickr_API_key);
        // Connects to cache database
		if( $flickr_cache_db ) {
			$f->enableCache('db', $flickr_cache_db,
				$flickr_cache_exp, $flickr_cache_table);
		}
    }

/*

=head3 PRIVATE: photos_config_page()

Hooks into WordPress Admin menu to manage options and whatnot.

=cut

*/
    function photos_config_page() {
        global $flickr_user, $flickr_API_key;
        global $flickr_cache_db, $flickr_cache_exp, $flickr_cache_table;

        // Add submenu to Plugins tab: Photos Flickr
        add_submenu_page('plugins.php',
            'Photos Flickr', 'Photos Flickr', 'manage_options',
            'photos-flickr-config', 'photos_flickr_config_page');
    }
    add_action('admin_menu', 'photos_config_page');

/*

=head3 PRIVATE: get_flickr_user()

Private function to map NSID, username, or e-mail to $flickr_user, which
will be an NSID.

Meant to be called by photos_init() as a convenience and also by the
Admin panel as a sanity-check.

Returns a $person ... I think.

=cut

*/
    function get_flickr_user( $f, $get_flickr_user ) {
        global $error_message;
        // DEFAULT: admin_email
        if( !$get_flickr_user ) {
            $get_flickr_user = get_option('admin_email');
        }
        if( !$get_flickr_user ) {
            $error_message .= "get_flickr_user: I don't think this ".
            "request is true: '$get_flickr_user'!";
            return(False);
        }
        if( $debug ) { echo "Looking for get_flickr_user == $get_flickr_user<br />"; }
        // CASE 1: Valid NSID
        $get_person = $f->people_getInfo($get_flickr_user);
        if( isset($get_person['id']) ) { 
            if( $debug ) { echo "get_flickr_user--NSID: ".$get_person['id']."<br />"; }
            return($get_person);
        }
        // CASE 2: Valid username
        $get_person = $f->people_findByUsername($get_flickr_user);
        if( isset($get_person['id']) ) { 
            if( $debug ) { echo "get_flickr_user--Username: ".$get_person['id']."<br />"; }
            return($get_person);
        }
        // CASE 3: Valid e-mail
        $get_person = $f->people_findByEmail($get_flickr_user);
        if( isset($get_person['id']) ) { 
            if( $debug ) { echo "get_flickr_user--Email: ".$get_person['id']."<br />"; }
            return($get_person);
        }
        // CASE 4: Nyet!
        $error_message .= "get_flickr_user: I tried to search by ".
            "NSID, username, and e-mail, but found nothing for ".
            "'$get_flickr_user'!";
        return(False);
    }

/*

=head3 PRIVATE: photos_flickr_config_page()

Displays the actual administrative configuration page.  Byagh!

=cut

*/

    function photos_flickr_config_page() {
    /* WTF: Stolen heavily from the Akismet plugin, which lacks
     * comments, so who knows what the f_ck I'm doing wrong here? */
        global $flickr_API_key, $flickr_user;
        global $f;
        global $error_message;

    // STEP 2: Parse form submissions
    // TODO: Do we need to check user authentication level!?
    if ( isset($_POST['submit']) ) {
        photos_init_phpFlickr();
        if( isset($_POST['flickr_API_key']) ) {
            $flickr_API_key = $_POST['flickr_API_key'];
            update_option('flickr_API_key', $flickr_API_key);
        }
        if( isset($_POST['flickr_user']) ) {
            $person = get_flickr_user( $f, $_POST['flickr_user'] );
            if( $person ) {
                $flickr_user = $person['id'];
                update_option('flickr_user', $flickr_user);
            }
        }
    }

    // STEP 1: Build a form ...
?>
<?php if ( !empty($_POST)  && empty($error_message) ) : ?>
<div id="message" class="updated fade"><p><strong><?php _e('Options saved.') ?></strong></p></div>
<?php elseif ( !empty($_POST) && !empty($error_message) ) : ?>
<div id="message" class="error"><p><strong><?php _e($error_message) ?></strong></p></div>
<?php endif; ?>
<div class="wrap">
    <h2>Photos Flickr</h2>
    <form action="" method="post" id="photos-flickr-config">
        <table class="optiontable">
            <tr valign="top">
                <th scope="row">Flickr User ID:</th>
                <td><input name="flickr_user" type="text"
                value="<?php echo "$flickr_user" ?>" />
                <br />Enter your NSID, Flickr screen name, or the e-mail
                address you used to register with Flickr.  Once found,
                this value will be converted to your NSID.  If you don't
                enter anything, we will try your
                <code>admin_email</code>.</td>
            </tr>
            <tr valign="top">
                <th scope="row">Flickr API Key:</th>
                <td><input name="flickr_API_key" type="text" size="32"
                class="code" value="<?php echo "$flickr_API_key" ?>" />
                <br /><b>Required!</b>  You will need to <a
                href="http://www.flickr.com/services/api/keys/apply/">request
                an API key from flickr.com</a> for this plugin to
                work.</td>
            </tr>
        </table>
    <p class="submit"><input type="submit" name="submit" value="Update Options &raquo;" /></p>
    </form>
</div>
<?php
;
    }

/*

=head3 PRIVATE: photos_private_img()

Return an IMG tag for a photo.

 Arguments:
    $photo  =   $photo object -- defaults to
                $f->photos_getInfo($GLOBAL['photo_id'])
    $size   =   One of: square, thumbnail, small, medium, large,
                original

If we don't have a $photo available, we can:

 $f = global $f;
 $photo = $f->photos_getInfo($photo_id);
 photos_private_img($photo, 'medium');

=cut

*/
    function photos_private_img( $photo = NULL, $size = 'square' ) {
        global $f;
        // If we aren't passed a legitimate photo object, let us
        // paint-by-number ...
        if(! $photo['id'] ) {
            if( $photo == NULL ) { // default?
                $photo = $GLOBAL['photo_id'];
            }
            whine("photos_private_img: doing paint-by-number: $photo");
            $photo = $f->photos_getInfo($photo);
        }
        // Now we do the real work
        $my_src     =   'src="'.$f->buildPhotoUrl($photo, $size).'"';
        $my_id      =   'id="photos_photo_'.$photo['id'].'"';
        $my_class   =   'class="photos_photo_'.$size.'"';
        //$my_alt     =   'alt="'.
        //                  htmlspecialchars($photo['title'], ENT_QUOTES).'"';
        $my_title   =   'title="'.
                          htmlspecialchars($photo['title'], ENT_QUOTES).'"';
        return "<img $my_src $my_id $my_class $my_title />";
    }

/*

=head3 PRIVATE: photos_private_getContext()

"MASTER" function for:
photos_(photo|photoset)_(next|prev)_(href|img|title)

 Arguments:
    $context    = (photo|photoset)
    $nl         = (next|prev)
    $attr       = (href|url|title)
    $photo_id   = photo ID for context
    $context_id = set ID for context
    $thumb_type = thumbnail type for img

We call it $context_id in the hope that we could one day give context
for, say, tags ...

=cut

*/

    function photos_private_getContext(
      $context = NULL, $nl = NULL, $attr = NULL,
      $photo_id = NULL, $context_id = NULL, $thumb_type = 'square' ) {
        global $f, $photos_base_url, $pretty_links;
        // There's no context without $photo_id ...
        if( $photo_id == NULL ) { $photo_id = $GLOBALS['photo_id']; }
        // There's no context without $photoset_id ...
        if( $context_id == NULL ) { $context_id = $GLOBALS['photoset_id']; }
        // "nextphoto" or "prevphoto"
        $nlphoto = $nl."photo";
        // return string
        $my_return = NULL;

        // First, look at our context
        if( $context == "photoset" ) {
            $my_gotContext = $f->photosets_getContext($photo_id, $context_id);
            if( $pretty_links ) {
                $my_photos_base_url = $photos_base_url."photoset/$context_id/";
            } else {
                $my_photos_base_url = $photos_base_url."photoset=$context_id&";
            }
        } else { // default to context == "photo"
            $my_gotContext = $f->photos_getContext($photo_id);
            $my_photos_base_url = $photos_base_url;
        }
        // Assign my_c_photo_id to the appropriate neighbor
        // "context photo id"
        $my_c_photo_id = $my_gotContext[$nlphoto][id];
        if( $my_c_photo_id != 0 ) { // if exists us
            if( $attr == "href" ) {
                if( $pretty_links ) {
    			    $my_return = $my_photos_base_url."photo/".$my_c_photo_id;
                } else {
    			    $my_return = $my_photos_base_url."photo=".$my_c_photo_id;
                }
            }
            elseif( $attr == "title" ) {
    			$my_return = $my_gotContext[$nlphoto]['title'];
            }
            elseif( $attr == "url" || $attr == "img" ) {
                // We work with my "context" photo
                $my_c_photo = $f->photos_getInfo($my_c_photo_id);
			    $my_return = photos_private_img($my_c_photo, $thumb_type);
                  // "<img class=\"photosThumb\" id=\"photosId_".
				  // $my_c_photo_id."\" src=\"".
				  // $f->buildPhotoURL($my_c_photo, $thumb_type).
				  // "\" title=\"".$my_gotContext[$nlphoto]['title'].
                  // "\" />";
            }
        }
        return $my_return;
    }

/*

PRIVATE: photos_flickr_hello()
    
"Hello World" function -- useful for testing

=cut

*/

	function photos_flickr_hello() {
		// Bring in our flickr object and NSID
		$f = $GLOBALS['f'];
		$nsid = $GLOBALS['nsid'];
        if( $debug ) { print "nsid: $nsid<br />\n"; }
	
	    // Get the friendly URL of the user's photos
	    $photos_url = $f->urls_getUserPhotos($nsid);
        if( $debug ) { print "photos_url: $photos_url<br />\n"; }
   
	    // Get the user's first 36 public photos
		$photos = $f->people_getPublicPhotos($nsid, NULL, 36);
   
	    // Loop through the photos and output the html
	    foreach ((array)$photos['photo'] as $photo) {
			print "<a href=$photos_url$photo[id]>";
	        //print "<img border='0' ". // alt='$photo[title]' ".
			//	"src=" . $f->buildPhotoURL($photo, "Square") . ">";
            print photos_private_img($photo, 'square');
	        print "</a>";
			$i++;
			// If it reaches the sixth photo, insert a line break
			if ($i % 6 == 0) {
				echo "<br>\n";
	     	}
		}
	}

/*

=head3 PRIVATE: whine()

Collect debug messages, emitting them in an apropriate manner, if
appropriate.

=cut

*/

    function whine($message = "but i don't know why!") {
        global $debug;
        if( $debug ) { echo "$message<br />"; }
    }

/*

=head2 OBSOLETED FUNCTIONS

Backwards-compatability is fun!

=cut

*/

	// PUBLIC: Print next photoset thumbnail (OBSOLETED)
	function photos_photoset_next_url( $photoset_id = NULL, $thumb_type = 'square',
      $photo_id = NULL ) {
        print photos_private_getContext("photoset", "next", "url",
          $photo_id, $photoset_id, $thumb_type);
	}

	// PUBLIC: Print prev photoset thumbnail (OBSOLETED)
	function photos_photoset_prev_url( $photoset_id = NULL, $thumb_type = 'square',
      $photo_id = NULL ) {
        print photos_private_getContext("photoset", "prev", "url",
          $photo_id, $photoset_id, $thumb_type);
	}

/*

=head3 OBSOLETED: photos_index()

Obsoleted -- passes through to photos_photo_index().

=cut

*/
	function photos_index(
		$num_per_page = 25, $num_per_row = 5, $page = NULL,
		$thumb_type='square', $thumb_before = '', $thumb_after = '',
		$row_sep = '<br />' ) {
        photos_photo_index($num_per_page, $num_per_row, $page,
          $thumb_type, $thumb_before, $thumb_after, $row_sep);
    }

/*

=head3 OBSOLETED: photos_pageindex()

Obsoleted -- passes through to photos_photo_pageindex().

=cut

*/

	function photos_pageindex( $context = 2, $num_per_page = 25,
		$item_sep = ', ', $dotdotdot = " . . . ",$page = NULL ) {
        photos_photo_pageindex($context, $num_per_page, $item_sep,
          $dotdotdot, $page);
    }

/*

=head3 OBSOLETED: photos_index_href()

Obsoleted.

=cut

*/
    function photos_index_href() {
//        global $photos_base_url;
//        print $photos_base_url;
        print get_permalink();
    }

/*

=head3 OBSOLETED: photos_photo_next_img()

Print IMG link for next photo in stream.

Accepts Flickr image size type as first argument: defaults to "square"

=head3 OBSOLETED: photos_photo_prev_img()

Print IMG link for previous photo in stream.

Accepts Flickr image size type as first argument: defaults to "square"

=cut

*/

	// PUBLIC: Print next photo thumbnail (OBSOLETED)
	function photos_photo_next_url( $thumb_type = 'square',
      $photo_id = NULL ) {
        print photos_private_getContext("photo", "next", "url",
          $photo_id, NULL, $thumb_type);
	}
	// PUBLIC: Print prev photo thumbnail (OBSOLETED)
	function photos_photo_prev_url( $thumb_type = 'square',
      $photo_id = NULL ) {
        print photos_private_getContext("photo", "prev", "url",
          $photo_id, NULL, $thumb_type);
	}

/*

=head3 OBSOLETED: photos_photoset_url() 

Print primary image URL of $photoset_id.  (OBSOLETED)

=cut

*/

    function photos_photoset_url( $photoset_id = NULL, $thumb_type = 'square' ) {
        photos_photoset_img($photoset_id, $thumb_type);
    }

?>
