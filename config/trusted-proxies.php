<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | When behind a load balancer or reverse proxy (Traefik, nginx, etc.),
    | specify the proxy IP addresses or CIDR ranges to trust. Use '*' only
    | when behind a single fully trusted proxy (e.g. AWS ELB with unknown IPs).
    |
    | Production: Restrict to actual proxy IPs (e.g. 172.18.0.0/16 for Docker,
    | or your Traefik/load balancer IPs). Never use '*' in production unless
    | you have no alternative (e.g. cloud load balancer with dynamic IPs).
    |
    */

    'at' => env('TRUSTED_PROXIES', '127.0.0.1') === '*'
        ? '*'
        : array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1'))),

];
