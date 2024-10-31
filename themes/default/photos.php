<?php
	/*
	Template Name: Photos
	*/
?>
<?php photos_init(); ?>
<!-- ?php get_header(); ? -->
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>

<head profile="http://gmpg.org/xfn/11">
<meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php bloginfo('charset'); ?>" />

<title><?php bloginfo('name'); ?> <?php if ( is_single() ) { ?> &raquo; Blog Archive <?php } ?> <?php wp_title(); ?></title>

<meta name="generator" content="WordPress <?php bloginfo('version'); ?>" /> <!-- leave this for stats -->

<link rel="stylesheet" href="<?php bloginfo('stylesheet_url'); ?>" type="text/css" media="screen" />
<link rel="alternate" type="application/rss+xml" title="<?php bloginfo('name'); ?> RSS Feed" href="<?php bloginfo('rss2_url'); ?>" />
<link rel="pingback" href="<?php bloginfo('pingback_url'); ?>" />

<style type="text/css" media="screen">

		<?php if (photos_is_photo()): ?>
	#page { background: url("<?php bloginfo('stylesheet_directory'); ?>/images/kubrickbg-ltr.jpg") repeat-y top; border: none; }
        <?php else : ?>
	#page { background: url("<?php bloginfo('stylesheet_directory'); ?>/images/kubrickbgwide.jpg") repeat-y top; border: none; }
        <?php endif; ?>

</style>

<?php wp_head(); ?>
</head>
<body>
<div id="page">


<div id="header">
	<div id="headerimg">
		<h1><a href="<?php echo get_option('home'); ?>/"><?php bloginfo('name'); ?></a></h1>
		<div class="description"><?php bloginfo('description'); ?></div>
	</div>
</div>
<hr />

		<?php if (photos_is_photo()): ?>
    <!-- PHOTO -->
	<div id="content" class="narrowcolumn">

		<div class="post">
		    <h2><?php photos_photo_title(); ?></h2>
			<div class="entry">
            	<p style="text-align: center;">
            	<a href="<?php photos_photo_page_url(); ?>">
           	    <img style="border: solid 1px black;"
                    src="<?php photos_photo_url('medium'); ?>" /></a>
                </p>
            	<p><?php photos_photo_description(); ?></p>

                <p class="postmetadata alt">This photograph was taken
                  <?php photos_photo_date_taken() ?>.
                  <?php if( photos_photo_tag_list() ) : ?>
                    <br />It is tagged as:
                    <?php photos_photo_tags('', ', '); ?>.
                    <!-- 
                    <br />Though, I could also say it is tagged as:
                    <?php foreach( photos_photo_tag_list() as $tag )
                        echo $tag['raw'].", "; ?>
                    -->
                  <?php endif; ?>
                </p>
			</div>
		</div>
    </div>

        <?php elseif( photos_is_index() == "photo" ) : ?>
    <!-- PHOTO INDEX -->
	<div id="content" class="widecolumn">
        
            <?php if ($_REQUEST['phototag']): ?>
        <!-- by tag ... -->
        <h2>Photographs tagged "<?php print $_REQUEST['phototag']?>"</h2>
            <?php elseif ($_REQUEST['photoset']): ?>
        <!-- by tag ... -->
        <h2><?php photos_photoset_title() ?></h2>
           	<?php else: ?>
        <!-- just an index -->
  		<h2>Photographs</h2>
            <?php endif; ?>
        <p>Browse Photos: <a href="<?php photos_photo_index_href(); ?>">All</a>
          | <a href="<?php photos_photoset_index_href(); ?>">Sets</a></p>

        <div class="entry">
            <!-- the index proper -->
            <p style="text-align: center;">
           	<!-- ?php photos_photo_index(25, 5, '', 'square', '', '', '<br />' ); ? -->
           	<?php photos_photo_index(16, 4, NULL,
              'thumbnail', '<br />', '',
              '<br clear="left" />',
              '<div style="width: 110px; float: left; text-align: center;">',
              '</div>',
              21, '<b>', '</b>',
              77, '<p style="text-align: left; margin-top: 0;
              margin-left: .5em;">', '</p>'
            ); ?>
            </p><br clear="all" />
            <?php if ($_REQUEST['photoset']): ?>
            <p><?php photos_photoset_description(); ?></p>
            <?php endif; ?>
      		<p>Pages: <?php photos_photo_pageindex(3, 16); ?></p>
            </div>
	</div>
        <?php elseif( photos_is_index() == "photoset" ) : ?>
    <!-- PHOTOSET INDEX -->
	<div id="content" class="widecolumn">
        <h2>Photosets</h2>
        <p>Browse Photos: <a href="<?php photos_photo_index_href(); ?>">All</a>
          | <a href="<?php photos_photoset_index_href(); ?>">Sets</a></p>
            <div class="entry">
                <!-- the index proper -->
                <?php photos_photoset_index(
                  '', '', 'square', true, true,
                  '<table style="float: left; width: 225px; height: 75px; border: 1px #dddddd dotted;">', '</table>',
                  '<tr><td style="width: 75px;">', '</td>',
                  '<td align=left>', '',
                  '<br /><span style="font-size: smaller;">(', ' photos)</span></td></tr>'
              ); ?>
            </div>
	</div>
		<?php endif; ?>


<!-- ?php get_sidebar(); ? -->
<?php include (TEMPLATEPATH . "/photos-sidebar.php"); ?>

<?php get_footer(); ?>
