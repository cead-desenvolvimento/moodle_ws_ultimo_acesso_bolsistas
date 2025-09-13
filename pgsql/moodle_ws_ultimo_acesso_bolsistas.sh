#!/usr/bin/env csh

set eh_hoje=`php /root/moodle_ws_ultimo_acesso_bolsistas/moodle_ws_ultimo_acesso_bolsistas.php`

if ($eh_hoje == 0) then
    exit 1
endif

php /root/moodle_ws_ultimo_acesso_bolsistas/index.php escondec10
