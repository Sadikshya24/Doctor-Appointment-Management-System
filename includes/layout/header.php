<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'MedScape'; ?>
    </title>
    <?php $basePath = isset($isSubFolder) && $isSubFolder ? '../' : ''; ?>
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/common/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="https://cdn-icons-png.flaticon.com/512/2966/2966327.png">
</head>

<body>