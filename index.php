<?php
session_start();
?>
<!DOCTYPE html>
<html>
    <head>
        <title>AJAX Grabber</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="css/bootstrap.min.css"/>
        <script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
        <script src="js/bootstrap.min.js"></script>
        <script src="js/script.js"></script>
    </head>
    <body>
        <div class="container">
            <div class="panel panel-default">
                <div class="panel-heading">AJAX log</div>
                <div class="panel-body">
                    <textarea class="form-control" disabled="disabled" style="resize: none; height: 200px;"></textarea>
                </div>
            </div>
            <div class="col-md-12" style="text-align: center;">
                <button id="begin" class="btn btn-primary">Begin</button>
            </div>
        </div>
    </body>
</html>
