#!/bin/bash

pkill -f nginx || true
pkill -f php-fpm || true
sleep 2

cat <<'EOF' > /etc/nginx/sites-available/default
server {
    listen 8080;
    root /home/site/wwwroot/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
EOF

chmod -R 775 /home/site/wwwroot/storage /home/site/wwwroot/bootstrap/cache

/usr/local/sbin/php-fpm -D
nginx -g "daemon off;"