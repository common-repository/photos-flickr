<div id="sidebar">

<?php if (photos_is_photo()): ?>

    <h2>Photos</h2>
    <div style="border: 1px #dddddd dotted; text-align: center;">
        <div class="alignleft">
            <a href="<?php photos_photo_prev_href(); ?>"><?php
              photos_photo_prev_url('square'); ?></a>
            <br />&laquo;
        </div>

        <div class="alignright"> 
            <a href="<?php photos_photo_next_href(); ?>"><?php
              photos_photo_next_url('square'); ?></a>
            <br />&raquo;
        </div>
        <br clear="right" />
        <a href="<?php photos_index_href(); ?>">Index of Photos</a>
    </div>

    <!-- clears the DIV floating alignright -->

    <?php if( $_REQUEST['photoset'] ) : ?>
    <h2>Photoset &raquo; <?php photos_photoset_title($_REQUEST['photoset']); ?></h2>
    <div style="border: 1px #dddddd dotted; text-align: center;">
        <div class="alignleft">
            <a href="<?php photos_photoset_prev_href($_REQUEST['photoset']); ?>"><?php photos_photoset_prev_url($_REQUEST['photoset'], 'square'); ?></a>
            <br />&laquo;
        </div>
        <div class="alignright"> 
            <a href="<?php photos_photoset_next_href($_REQUEST['photoset']); ?>"><?php photos_photoset_next_url($_REQUEST['photoset'], 'square'); ?></a>
            <br />&raquo;
        </div>
        <br clear="right" />
        <a href="<?php photos_photoset_href($set); ?>">Index of <?php photos_photoset_title($set); ?></a>
    </div>
    <?php endif; ?>

    <?php if( photos_photo_photoset_list() ) : ?>
    <h2>Photosets &raquo; <?php photos_photo_title(); ?></h2>
    <ul>
        <?php foreach( photos_photo_photoset_list() as $set ) : ?>
        <li style="border: 1px #dddddd dotted;">
            <table border=0>
                <tr>
                    <td><?php photos_photoset_url($set); ?></td>
                    <td><a href="<?php photos_photoset_href($set); ?>"><?php
                      photos_photoset_title($set); ?></a></td>
                </tr>
            </table>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

<?php endif; ?>

</div>
