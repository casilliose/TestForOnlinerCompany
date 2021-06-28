<html>
    <head></head>
    <body>
        <?php foreach ($rows as $row): ?>
            <?php
                $year = substr($row['date'], 3, 4);
                $month = substr($row['date'], 7, 2);
                $day = substr($row['date'], 9, 2);
            ;?>
            <div>Date: <?= $year?>-<?=$month?>-<?=$day?><br> Page: <?=$row['url'] ?><br> count: <?= $row['count']?> </div>
            <hr>
        <?php endforeach;?>
    </body>
</html>