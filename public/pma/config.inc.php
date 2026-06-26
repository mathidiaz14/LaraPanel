<?php
$cfg['blowfish_secret'] = 'xVkaZyyRCu6Wov4yvDCm1gYYpeJS16R1';
$i = 0;
$i++;
$cfg['Servers'][$i]['auth_type'] = 'signon';
$cfg['Servers'][$i]['SignonSession'] = 'SignonSession';
// $cfg['Servers'][$i]['SignonURL'] = '/login'; // Si falla, va al panel
$cfg['Servers'][$i]['host'] = 'localhost';
$cfg['Servers'][$i]['compress'] = false;
$cfg['Servers'][$i]['AllowNoPassword'] = true;
$cfg['UploadDir'] = '';
$cfg['SaveDir'] = '';