#!/bin/csh

set eh_hoje=`php /root/moodle_ws_ultimo_acesso_bolsistas/moodle_ws_ultimo_acesso_bolsistas.php`

if ($eh_hoje == 0) then
    exit 1
endif

php /root/moodle_ws_ultimo_acesso_bolsistas/index.php > /root/moodle_ws_ultimo_acesso_bolsistas/relatorio.tmp.html
iconv -s -f ISO-8859-1 -t UTF-8 /root/moodle_ws_ultimo_acesso_bolsistas/relatorio.tmp.html > /root/moodle_ws_ultimo_acesso_bolsistas/relatorio.html
xhtml2pdf /root/moodle_ws_ultimo_acesso_bolsistas/relatorio.html /root/moodle_ws_ultimo_acesso_bolsistas/relatorio.pdf

mutt -s "Relatorio de acesso ao Moodle ${eh_hoje}" < /dev/null financeiro.cead@ufjf.br -b rodrigo.marangon@ufjf.br -a /root/moodle_ws_ultimo_acesso_bolsistas/relatorio.pdf
mutt -s "Relatorio de acesso ao Moodle ${eh_hoje}" < /dev/null rodrigo.marangon@ufjf.br -a /root/moodle_ws_ultimo_acesso_bolsistas/relatorio.html

rm /root/moodle_ws_ultimo_acesso_bolsistas/relatorio.tmp.html /root/moodle_ws_ultimo_acesso_bolsistas/relatorio.html /root/moodle_ws_ultimo_acesso_bolsistas/relatorio.pdf
