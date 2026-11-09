<?php
$cfg['blowfish_secret'] = "6b436aae844215e8cd99c4255af0b81d";
$i = 0;
$i++;
$cfg['Servers'][$i]['auth_type'] = 'cookie';
// $cfg['Servers'][$i]['SignonSession'] = 'SignonSession';
// $cfg['Servers'][$i]['SignonURL'] = '/login';
$cfg['Servers'][$i]['host'] = 'localhost';
$cfg['Servers'][$i]['compress'] = false;
// Hardening 2026-09-30: era `true`. /pma es una ruta pública predecible y la
// cuenta root@localhost de MySQL está sin password en este servidor, así que
// AllowNoPassword=true exponía el control completo de MySQL sin autenticación
// alguna. Con false, el login por cookie exige siempre un password real.
$cfg['Servers'][$i]['AllowNoPassword'] = false;
$cfg['UploadDir'] = '';
$cfg['SaveDir'] = '';