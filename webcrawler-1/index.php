<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Web Crawler</title>
    </head>
    <body>
        <h1>Web Crawler Project - 1</h1>
        <?php
            include __DIR__.'/qp/qp.php';

            $initial_url = 'https://pro.beatport.com/genre/deep-house/12/tracks';
            $content = file_get_contents($initial_url);

            //load qp with content fetched and initialise from body tag
            $qp = htmlqp($content,'body');
            echo '<pre>';
            if($qp->length>0){
                //we have some data to parse.
                $tracks = $qp->find('.track');
                foreach($tracks as $track){

                    echo 'Track Found:'.$track->find('.buk-track-primary-title')->first()->text()."\r\n";

                }
            }

        ?>
    </body>
</html>
