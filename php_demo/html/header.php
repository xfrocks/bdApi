<!doctype html>
<html lang=en>
<head>
    <meta charset=utf-8>
    <title>[bd] API - PHP Demo</title>
    <style>
        a {
            color: blue;
        }

        input[type=text],
        input[type=button] {
            margin-right: 5px;
            max-width: 90%;
            width: 400px;
        }

        .code {
            font-family: "Courier New", Courier, monospace;
            white-space: pre;
        }

        .pl-e {
            color: #795da3;
        }

        .pl-ent {
            color: #63a35c;
        }

        .pl-k, .pl-s, .pl-st {
            color: #a71d5d;
        }

        .pl-s1 {
            color: #df5000;
        }

        .pl-s3 {
            color: #0086b3;
        }

        div.request {
            overflow: hidden;
            padding: 5px;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 100%;
        }

        div.response {
            background: #d3d3d3;
            max-height: 400px;
            overflow: scroll;
            padding: 5px;
            white-space: nowrap;
            width: 100%;
        }
    </style>
</head>
<body>

<?php if (!empty($config['api_root'])): ?>
<h1><a href="<?php echo $config['api_root']; ?>" target="_blank"><?php echo $config['api_root']; ?></a></h1>

<ul>
    <li>API Key: <?php echo $config['api_key']; ?></li>
    <li>API Secret: <?php echo $config['api_secret']; ?></li>
    <li>API Scope: <?php echo $config['api_scope']; ?></li>
    <?php if (empty($_SERVER['SCRIPT_NAME']) || basename($_SERVER['SCRIPT_NAME']) != 'setup.php'): ?>
        <li><a href="setup.php">Re-setup</a></li>
    <?php endif; ?>
</ul>
<hr/>
<?php endif; ?>