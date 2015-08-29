<?php

class MyDB extends SQLite3 {

    function __construct() {
        $this->open(__DIR__ . '/beatprot.sqlite');
    }

}

if (!file_exists(__DIR__ . '/beatprot.sqlite')) {
    $db = new MyDB();
    //added artist_spotify_id in album table to identify that the album relates to which artist.
    $db->exec('CREATE TABLE IF NOT EXISTS "album" ("id" INTEGER PRIMARY KEY  NOT NULL ,"Album_name" VARCHAR(255) NOT NULL ,"Album_id" varchar(255) NOT NULL,"Artist_Spotify_id" varchar(255) )');
    $db->exec('CREATE TABLE IF NOT EXISTS "artist" ("id" INTEGER PRIMARY KEY  NOT NULL ,"Artist_name" VARCHAR(255) NOT NULL ,"Artist_Spotify_id" varchar(255) )');

    //added album ID to tracks table to identify track related to which album.
    $db->exec('CREATE TABLE IF NOT EXISTS "tracks" ("id" INTEGER PRIMARY KEY  NOT NULL ,"Artist" VARCHAR(255) NOT NULL ,"Album" varchar(255) NOT NULL ,"Album_id" varchar(255) NOT NULL,"Track" varchar(255) NOT NULL, "Link" TEXT )');
    $db->exec('CREATE TABLE IF NOT EXISTS "beatprottracks" ("id" INTEGER PRIMARY KEY  NOT NULL ,"track_name" VARCHAR(255) NOT NULL ,"artist" varchar(255) NOT NULL ,"link" TEXT )');
    $db->exec('CREATE TABLE IF NOT EXISTS "display" ("id" INTEGER PRIMARY KEY  NOT NULL ,"Track" VARCHAR(255) NOT NULL ,"Artist" varchar(255) NOT NULL ,"uri" TEXT )');
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
        require '/spotify-web-api-php-master/src/SpotifyWebAPI.php';
        require '/spotify-web-api-php-master/src/Request.php';

        $api = new SpotifyWebAPI\SpotifyWebAPI();

        $pages = array('https://pro.beatport.com/genre/deep-house/12/tracks');
        $done = array();

        $final_page = isset($_GET['pages']) ? $_GET['pages'] : 2;

        echo '<pre>';
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
                    $artist_spotify_id = $db->querySingle('select Artist_spotify_id from artist where Artist_name="' . $artist_name . '"');
                    echo '<br>';
                    echo 'Artist id: ' . $artist_spotify_id;
                    echo '<br>';
                    if (!$artist_spotify_id) {
                        $spotify_artist = $api->search($artist, 'artist');
                        echo '<br>';
                        echo 'NOT FOUND IN DATABASE';
                        echo '<br>';

                        //Geting artist id via Spotify Api
                        foreach ($spotify_artist->artists->items as $spotify_id) {

                            $artist_name = $spotify_id->name;
                            $artist_spotify_id = $spotify_id->id;
                            echo 'Spotify id:'.$spotify_id->id;
                            echo 'Artist name:'.$artist_name;
                            if ($spotify_artist and $spotify_id) {
                                if (strpos($spotify_id->name, $artist) !== false) {
                                    $db->exec('insert into artist ("Artist_name","Artist_spotify_id") values ("' . $artist_name . '","' . $artist_spotify_id . '")');
                                }
                            }
                        }
                    }
                    //HERE I MUST HAVE ARTIST ID. EITHER FROM DATABASE OR SPOTIFY
                    // NOW DO THE SAME PROCESS FOR ARTIST ALBUMS.
                    
                    if ($artist_spotify_id and $artist_spotify_id != 'No_id') {

                        $artist_albums = $api->getArtistAlbums($artist_spotify_id);

                        foreach ($artist_albums->items as $album) {
                            $spotify_album_name = $album->name;
                            $spotify_album_id = $album->id;

                            $album_id = $db->querySingle('select id from album where Album_id="' . $spotify_album_id . '" ');

                            if (!$album_id) {
                                echo 'Trying to find';
                                $db->exec('insert into album ("Album_name","Album_id", "Artist_spotify_id") values ("' . $spotify_album_name . '","' . $spotify_album_id . '","' . $artist_spotify_id . '")');

                                $tracks_in_album = $db->querySingle('select id from tracks where Album_id="' . $spotify_album_id . '" ');

                                $spotify_tracks = $api->getAlbumTracks($spotify_album_id);

                                foreach ($spotify_tracks->items as $track_name) {

                                    $spotify_track_name = $track_name->name;
                                    $spotify_track_uri = $track_name->uri;

                                    $track_id = $db->querySingle('select id from tracks where "Track"="' . $spotify_track_name . '" and Link="' . $spotify_track_uri . '"');
                                    if (!$track_id) {
                                        $db->exec('insert into tracks ("Track","Artist","Link", "Album", "Album_id") values ("' . $spotify_track_name . '","' . $artist . '","' . $spotify_track_uri . '","' . $spotify_album_name . '","' . $spotify_album_id . '")');
                                    }
                                }
                            } elseif ($album_id) {
                                echo 'Album in local base';

                                $tracks_in_album = $db->querySingle('select id from tracks where Album_id="' . $spotify_album_id . '" ');
                                if ($tracks_in_album) {
                                    echo '<br>';
                                    echo 'Skipping';
                                    echo '<br>';
                                    continue;
                                }

                                $spotify_tracks = $api->getAlbumTracks($spotify_album_id);
                                foreach ($spotify_tracks->items as $track_name) {

                                    $spotify_track_name = $track_name->name;
                                    $spotify_track_uri = $track_name->uri;

                                    $track_id = $db->querySingle('select id from tracks where "Track"="' . $spotify_track_name . '" and Link="' . $spotify_track_uri . '"');
                                    if (!$track_id) {
                                        $db->exec('insert into tracks ("Track","Artist","Link", "Album", "Album_id") values ("' . $spotify_track_name . '","' . $artist . '","' . $spotify_track_uri . '","' . $spotify_album_name . '","' . $spotify_album_id . '")');
                                    }
                                }
                            }
                        }
                    }
                    $id = $db->querySingle('select id from beatprottracks where track_name="' . $title . '" and artist="' . $artist . '"');
                    if ($id) {
                        $db->exec('update beatprottracks set link="' . $link_to_track . '" where id=' . $id);
                    } else {
                        $db->exec('insert into beatprottracks ("track_name","artist","link") values ("' . $title . '","' . $artist . '","' . $link . '")');
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

        $id_c = 0;

        echo '<table border = "1" style = "width:100%">';
        echo '<tr>';

        while (true) {

            $id_c ++;

            $check_id = $db->querySingle('select artist from beatprottracks where id="' . $id_c . '"');

            if (empty($check_id)) {
                echo '<br>';
                echo 'Last row. Breaking the loop.';
                echo '<br>';
                break;
            }

            $get_track = $db->querySingle('select track_name from beatprottracks where id="' . $id_c . '" and artist="' . $check_id . '"');

            $get_link = $db->querySingle('select Link from tracks where "Track" LIKE  "%' . $get_track . '%" and Artist LIKE  "' . $check_id . '"');


            if ($get_link) {

                $db->exec('insert into display ("Artist","Track","uri") values ("' . $check_id . '","' . $get_track . '","' . $get_link . '")');

                echo '</tr>';
                echo '<td>' . $check_id . '</td>';
                echo '<td>' . $get_track . '</td>';
                echo '<td> <a href=' . $get_track . '>' . $get_link . '</a> </td>';
                #echo '<td>' . $get_link . '</td>';
                echo '<tr>';
            }
        }
        echo '</table>';
        ?>
    </body>
</html>