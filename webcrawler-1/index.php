<?php
class MyDB extends SQLite3
{
    function __construct()
    {
        $this->open(__DIR__.'/beatprot.sqlite');
    }
}

if(!file_exists(__DIR__.'/beatprot.sqlite')){
    $db = new MyDB();
    $db->exec('CREATE TABLE IF NOT EXISTS "spotify" ("id" INTEGER PRIMARY KEY  NOT NULL ,"Artist" VARCHAR(255) NOT NULL ,"Album" varchar(255) NOT NULL ,"Track" varchar(255) NOT NULL, "Link" TEXT )');
    $db->exec('CREATE TABLE IF NOT EXISTS "beatprottracks" ("id" INTEGER PRIMARY KEY  NOT NULL ,"track_name" VARCHAR(255) NOT NULL ,"artist" varchar(255) NOT NULL ,"link" TEXT )');
}else{
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
        
        function getFile($url){


            $url_hash = md5($url);
            if(file_exists($url_hash)){
                return file_get_contents($url_hash);
            }else{
                $content = file_get_contents($url);
                file_put_contents($url_hash, $content);
                return $content;
            }

        }

            include __DIR__.'/qp/qp.php';
            require '/spotify-web-api-php-master/src/SpotifyWebAPI.php';
            require '/spotify-web-api-php-master/src/Request.php';
            
            $api = new SpotifyWebAPI\SpotifyWebAPI();
            
            $pages = array('https://pro.beatport.com/genre/deep-house/12/tracks');
            $done = array();

            $final_page = isset($_GET['pages'])?$_GET['pages']:2;

            echo '<pre>';
            $ipCount = 0;
            while ($pages) {

                set_time_limit(0);

                $link = array_shift($pages);
                $done[] = $link;
                //$content = file_get_contents($link);
                $content = getFile($link);

                //load qp with content fetched and initialise from body tag
                $htmlqp = @htmlqp($content,'body');

                if($htmlqp->length>0){
                    //we have some data to parse.
                    $tracks = $htmlqp->find('.track');
                    foreach($tracks as $track){
                        
                        $title = $track->find('.buk-track-primary-title')->first()->text();
                        $artist = $track->find('.buk-track-artists > a')->first()->text();
                        $link_to_track = 'https://pro.beatport.com'.$track->find('.buk-track-title > a')->first()->attr('href');
                        
                        $spotify_artist = $api->search($artist, 'artist');
                        
                        //Geting artist id via Spotify Api
                        
                        foreach ($spotify_artist->artists->items as $spotify_id) {

                            if (strpos($spotify_id->name,$artist) !== false) {
                            echo '<br>';
                            echo '<br>';
                            echo 'Beatport name is: '.$artist, '<br>';
                            echo '<br>';
                            echo 'Spotify name is: '.$spotify_id->name, '<br>';
                            echo '<br>';
                            echo 'Artist id on spotify is: '.$spotify_id->id, '<br>';
                            
                            // Getting artist albums using artist id on Spotify
                            
                            $artist_albums = $api->getArtistAlbums($spotify_id->id);
                                 foreach ($artist_albums->items as $album) {
                                 echo '<br>';
                                 echo $album->name. ' ' .$album->id . '<br>';
                                 
                                 $spotify_album_id = $album->id;
                                 $spotify_album_name = $album->name;
                                 
                                 // Getting albums tracks using album id
                                 
                                 echo '<br>';
                                 echo 'Album tracks:';
                                 echo '<br>';
                                 $spotify_tracks = $api->getAlbumTracks($spotify_album_id);
                                 
                                 foreach ($spotify_tracks->items as $track_name) {
                                 
                                 $spotify_track_name = $track_name->name;
                                 $spotify_track_uri = $track_name->uri;
                                     
                                 echo '<b>' . $spotify_track_name . ' '. $spotify_track_uri .'</b> <br>';
                                 
                                 // Caching tracks to local database
                                 
                                 $id_s = $db->querySingle('select id from spotify where "track"="'.$title.'" and artist="'.$artist.'"');
                                 
                                if($id_s){
                                $db->exec('update spotify set Link="'.$spotify_track_uri.'" where id='.$spotify_track_name);
                                }else{
                                $db->exec('insert into spotify ("Track","Artist","Link", "Album") values ("'.$spotify_track_name.'","'.$artist.'","'.$spotify_track_uri.'","'.$spotify_album_name.'")');
                                }
                                }
                                }
                                }
                                
                            else {
                                echo '<br>';
                                echo 'Not matched!', '<br>';
                                echo 'Beatport name is: '.$artist, '<br>';
                                echo 'Spotify name is: '.$spotify_id->name, '<br>';   
                                 }
                            }
                        
                        $id = $db->querySingle('select id from beatprottracks where track_name="'.$title.'" and artist="'.$artist.'"');
                        if($id){
                           $db->exec('update beatprottracks set link="'.$link_to_track.'" where id='.$id);
                        }else{
                            $db->exec('insert into beatprottracks ("track_name","artist","link") values ("'.$title.'","'.$artist.'","'.$link.'")');
                        }
                        }
                    $next_page = $htmlqp->find('.pag-next');
                    if($next_page->length>0){
                        $pages = array('https://pro.beatport.com'.$next_page->first()->attr('href'));
                    }else
                        $pages = false;
                }

                $ipCount++;

                if($ipCount==$final_page)
                    $pages = false;

            }
        ?>
    </body>
</html>