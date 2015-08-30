<?php

class MyDB extends SQLite3 {

    function __construct() {
        $this->open(__DIR__ . '/beatprot.sqlite');
    }

}

if (!file_exists(__DIR__ . '/beatprot.sqlite')) {
    $db = new MyDB();
    //added artist_spotify_id in album table to identify that the album relates to which artist.
    $db->exec('CREATE TABLE IF NOT EXISTS "album" ("id" INTEGER PRIMARY KEY  NOT NULL ,'
            . '"Album_name" VARCHAR(255) NOT NULL ,'
            . '"Album_id" varchar(255) NOT NULL,'
            . '"Artist_Spotify_id" varchar(255) )');

    $db->exec('CREATE TABLE IF NOT EXISTS "artist" ("id" INTEGER PRIMARY KEY  NOT NULL ,'
            . '"Artist_name" VARCHAR(255) NOT NULL ,'
            . '"Artist_Spotify_id" varchar(255) )');

    $db->exec('CREATE INDEX artist_name ON artist (Artist_name ASC)');
    $db->exec('CREATE INDEX artist_albums ON album (Artist_Spotify_id ASC)');

    //added album ID to tracks table to identify track related to which album.
    $db->exec('CREATE TABLE IF NOT EXISTS "tracks" ("id" INTEGER PRIMARY KEY  NOT NULL ,'
            . '"Artist" VARCHAR(255) NOT NULL ,'
            . '"Album" varchar(255) NOT NULL ,'
            . '"Album_id" varchar(255) NOT NULL,'
            . '"Track" varchar(255) NOT NULL,'
            . ' "Link" TEXT )');
    $db->exec('CREATE INDEX albums_tracks ON tracks (Album_id ASC)');

    $db->exec('CREATE TABLE IF NOT EXISTS "beatprottracks" ("id" INTEGER PRIMARY KEY  NOT NULL ,'
            . '"track_name" VARCHAR(255) NOT NULL ,'
            . '"artist" varchar(255) NOT NULL ,'
            . '"link" TEXT )');

    $db->exec('CREATE TABLE IF NOT EXISTS "display" ("id" INTEGER PRIMARY KEY  NOT NULL ,'
            . '"Track" VARCHAR(255) NOT NULL ,'
            . '"Artist" varchar(255) NOT NULL ,'
            . '"uri" TEXT )');

} else {
    $db = new MyDB();
}
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Web Crawler</title>
    </head>
    <body>
        <h1>Web Crawler Project - 1</h1>
        <?php

        function getFile($url) {


            $url_hash = md5($url);
            if (file_exists($url_hash)) {
                return file_get_contents($url_hash);
            } else {
                $content = file_get_contents($url);
                file_put_contents($url_hash, $content);
                return $content;
            }
        }

        include __DIR__ . '/qp/qp.php';
        require __DIR__ .'/spotify-web-api-php-master/src/SpotifyWebAPI.php';
        require __DIR__ .'/spotify-web-api-php-master/src/Request.php';
        require __DIR__ .'/spotify-web-api-php-master/src/Session.php';
        require __DIR__ .'/spotify-web-api-php-master/src/SpotifyWebAPIException.php';

        $api = new SpotifyWebAPI\SpotifyWebAPI();

        $pages = array('https://pro.beatport.com/genre/deep-house/12/tracks');
        $done = array();

        $final_page = isset($_GET['pages']) ? $_GET['pages'] : 1;

        //echo '<pre>';
        $ipCount = 0;
        while ($pages) {

            set_time_limit(0);

            $link = array_shift($pages);
            $done[] = $link;
            //$content = file_get_contents($link);
            $content = getFile($link);

            //load qp with content fetched and initialise from body tag
            $htmlqp = @htmlqp($content, 'body');

            if ($htmlqp->length > 0) {
                //we have some data to parse.
                $tracks = $htmlqp->find('.track');
                foreach ($tracks as $track) {

                    $title = $track->find('.buk-track-primary-title')->first()->text();
                    $artist = $track->find('.buk-track-artists > a')->first()->text();
                    $link_to_track = 'https://pro.beatport.com' . $track->find('.buk-track-title > a')->first()->attr('href');

                    //CHECK IF ARTIST ALREADY EXIST IN DATABASE, PRIOR TO SEARCHING ON SPOTIFY
                    $artist_spotify_id = $db->querySingle("select Artist_spotify_id from artist where Artist_name='" . SQLite3::escapeString($artist) . "'"); //Like msq_realescape.

                    if (!$artist_spotify_id) { // If not in database -- get id via Spotify API.
                        $spotify_artist = $api->search($artist, 'artist');
                        //Geting artist id via Spotify Api
                        foreach ($spotify_artist->artists->items as $spotify_id) {

                            $artist_name = $spotify_id->name;
                            $artist_spotify_id_tmp = $spotify_id->id; // store in temp var. Why did you store id as temporary variable? Got it. To use in a bottom loop.

                             if ($artist_name === $artist) {
                                $artist_spotify_id = $spotify_id->id; //this is what we need
                                //echo '<br>';
                                //echo 'Artist name: ' . $artist_name;
                                //echo '<br>';
                            }
                            //will save all artist IDs in DB for caching
                            $db->exec('insert into artist ("Artist_name","Artist_spotify_id") values ('."'" . SQLite3::escapeString($artist_name) . "','" . $artist_spotify_id_tmp . "')");
                        }
                    }
                    //HERE I MUST HAVE ARTIST ID. EITHER FROM DATABASE OR SPOTIFY
                    // NOW DO THE SAME PROCESS FOR ARTIST ALBUMS.


                    //you dont  need it here. You must have artist spotify ID here from upper portion of source code block.
                    //$artist_id = $db->querySingle("select Artist_spotify_id from artist where Artist_name='" . SQLite3::escapeString($artist) . "'");

                    if ($artist_spotify_id) { //changed to artist spotify ID we found in upper code block.

                        $artist_albums = new stdClass(); //What is STD class? It's a class without a methods. You can give him yours.
                        $artist_albums->items = array(); //Creating dictionary.
                        $artist_albums_result = $db->query("select * from album where Artist_Spotify_id='" . SQLite3::escapeString($artist_spotify_id) . "'"); //Getting album dictionary using spotify id.
                        while ($album = $artist_albums_result->fetchArray()) { //Loop in this array to check if album in database.
                            //here you have database fetched artist albums. saved during last run. use it
                            $item = new stdClass(); //Declaring item class.
                            $item->name=$album['Album_name']; //Creating dictionary. 
                            $item->id=$album['Album_id'];
                            $artist_albums->items[] = $item; //Seting dictionary inside to database.
                        }

                        if(count($artist_albums->items)==0){ // search spotify for artist albums only if database does not contain any albums.
                            $artist_albums = $api->getArtistAlbums($artist_spotify_id);

                            foreach ($artist_albums->items as $album) {
                                $spotify_album_name = $album->name;
                                $spotify_album_id = $album->id;

                                $db->exec('insert into album ("Album_name","Album_id", "Artist_spotify_id") values ('."'" . SQLite3::escapeString($spotify_album_name) . "','" . $spotify_album_id . "','" . $artist_spotify_id . "')");
                            }

                        }

                       foreach ($artist_albums->items as $album) {
                            $spotify_album_name = $album->name;
                            $spotify_album_id = $album->id;

                            $spotify_tracks = new stdClass();
                            $spotify_tracks->items = array();
                            $spotify_tracks_result = $db->query("select * from tracks where Album_id='" . SQLite3::escapeString($spotify_album_id) . "'");
                            while ($track = $spotify_tracks_result->fetchArray()) { // Geting tracks using albums id in database. 
                                //here you have database fetched artist albums tacks. saved during last run. use it
                                $item = new stdClass(); 
                                $item->name = $track['Track'];
                                $item->external_urls = new stdClass();
                                $item->external_urls->spotify = $track['Link'];  //That's how you fill a dictionary.  If you don't get any tracks with this  -- fill the database with empty uri.
                                $spotify_tracks->items[] = $item;
                            }

                            if(count($spotify_tracks->items)==0){ // search spotify for artist tracks only if database does not contain any albums.
                                $spotify_tracks = $api->getAlbumTracks($spotify_album_id);

                                foreach ($spotify_tracks->items as $track) {
                                    $spotify_track_name = $track->name;
                                    $spotify_track_uri = $track->external_urls->spotify; //Filling the database with tracks using id.

                                    $db->exec('insert into tracks ("Track","Artist","Link", "Album", "Album_id") values ('."'"
                                                                    . SQLite3::escapeString($spotify_track_name) . "','"
                                                                    . SQLite3::escapeString($artist) . "','"
                                                                    . $spotify_track_uri . "','"
                                                                    . SQLite3::escapeString($spotify_album_name) . "','"
                                                                    . $spotify_album_id . "')");

                                }
                            }

                            /*if (!$album_id) {

                                $db->exec('insert into album ("Album_name","Album_id", "Artist_spotify_id") values ('."'" . SQLite3::escapeString($spotify_album_name) . "','" . $spotify_album_id . "','" . $artist_id . "')");

                                $tracks_in_album = $db->querySingle('select id from tracks where Album_id="' . $spotify_album_id . '" ');

                                $spotify_tracks = $api->getAlbumTracks($spotify_album_id);

                                foreach ($spotify_tracks->items as $track_name) {

                                    $spotify_track_name = $track_name->name;
                                    $spotify_track_uri = $track_name->uri;

                                    $track_id = $db->querySingle('select id from tracks where Track='."'" . SQLite3::escapeString($spotify_track_name) . "' and Link='" . $spotify_track_uri . "'");
                                    if (!$track_id) {
                                        $db->exec('insert into tracks ("Track","Artist","Link", "Album", "Album_id") values ('."'" . SQLite3::escapeString($spotify_track_name) . "','" . SQLite3::escapeString($artist) . "','" . $spotify_track_uri . "','" . SQLite3::escapeString($spotify_album_name) . "','" . $spotify_album_id . "')");
                                    }
                                }
                            } elseif ($album_id) {
                                continue;

                                $tracks_in_album = $db->querySingle('select id from tracks where Album_id="' . $spotify_album_id . '" ');
                                if ($tracks_in_album) {
                                    continue;
                                }

                                $spotify_tracks = $api->getAlbumTracks($spotify_album_id);
                                foreach ($spotify_tracks->items as $track_name) {

                                    $spotify_track_name = $track_name->name;
                                    $spotify_track_uri = $track_name->uri;

                                    $track_id = $db->querySingle('select id from tracks where Track='."'" . SQLite3::escapeString($spotify_track_name) . "' and Link='" . $spotify_track_uri . "'");
                                    if (!$track_id) {
                                        $db->exec('insert into tracks ("Track","Artist","Link", "Album", "Album_id") values ('."'" . SQLite3::escapeString($spotify_track_name) . "','" . SQLite3::escapeString($artist) . "','" . $spotify_track_uri . "','" . SQLite3::escapeString($spotify_album_name) . "','" . $spotify_album_id . "')");
                                    }
                                }
                            }*/
                        }
                    }
                    $id = $db->querySingle('select id from beatprottracks where track_name='."'" . SQLite3::escapeString($title) . "' and artist='" . SQLite3::escapeString($artist) . "'");
                    if ($id) {
                        $db->exec('update beatprottracks set link="' . $link_to_track . '" where id=' . $id);
                    } else {
                        $db->exec('insert into beatprottracks ("track_name","artist","link") values ('."'" . SQLite3::escapeString($title) . "','" . SQLite3::escapeString($artist) . "','" . $link . "')");
                    }
                }
                $next_page = $htmlqp->find('.pag-next');
                if ($next_page->length > 0) {
                    $pages = array('https://pro.beatport.com' . $next_page->first()->attr('href'));
                } else
                    $pages = false;
            }

            $ipCount++;

            if ($ipCount == $final_page)
                $pages = false;
        }
        ?>
        <table border = "1" style = "width:100%">
            <thead><tr>
                    <th>Track</th>
                    <th>Artist</th>
                    <th>Beat Prot Link</th>
                    <th>Spotify Link</th>
                </tr></thead>
            <tbody>
        <?php //Filling the database with tracks using id.
        $similar_tracks = $db->query('SELECT track_name,b.artist,b.link beatlink,t.Link spotify_link FROM beatprottracks as b join tracks as t on b.track_name=t.Track and b.artist=t.Artist'); 
        while ($track = $similar_tracks->fetchArray()) { ?> 
            <tr>
                <td><?php echo $track['track_name'];?></td>
                <td><?php echo $track['artist'];?></td>
                <td><?php echo $track['beatlink'];?></td>
                <td><?php echo $track['spotify_link'];?></td>
            </tr>
        <?php
        }
        echo '</tbody></table>';
        ?>
    </body>
</html>
