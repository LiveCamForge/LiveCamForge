# Nginx security example

Apache `.htaccess` files are ignored by Nginx. If the project directory is exposed directly by Nginx, add equivalent deny rules before publishing the site. Adapt paths to the virtual host.

```nginx
autoindex off;

location ~ ^/(?:app|bin|config|database|storage|templates|tests)(?:/|$) {
    deny all;
    return 404;
}

location ~ /(?:^|/)\. {
    deny all;
    return 404;
}

location ~* \.(?:log|sql|ini|bak|dist|example)$ {
    deny all;
    return 404;
}

location = /config/local.php {
    deny all;
    return 404;
}
```

Do not copy this blindly into an existing server block. Merge it with the hosting provider's PHP routing and test that `/admin/`, `/install/`, static assets, `robots.txt` and `sitemap.xml` still work.
