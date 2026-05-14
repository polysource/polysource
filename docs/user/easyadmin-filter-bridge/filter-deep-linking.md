# Filter URL deep linking (short token)

> Since `polysource/easyadmin-filter-bridge` v0.5.0.

Long `?filters[...]` URLs can hit email length limits, look
ugly in chat messages, and break when copy-pasted across
clients that truncate URLs. The short-token feature lets hosts
mint a 12-character hex token for a filter slice and expose a
clean `/admin/polysource/f/{token}` redirect.

Server-side feature — no JS shipped; hosts who want
"copy-to-clipboard" UX wire a small Stimulus controller.

## Usage

Render a "Copy share link" button on the filter chips bar:

```twig
{% block content_header %}
    {{ parent() }}
    {{ polysource_filter_share_button('orders', 'Copy share link') }}
{% endblock %}
```

The helper output is empty when there are no active filters —
no point shortening "no filters". When filters are active, it
renders:

```html
<a href="https://your-host/admin/polysource/f/aabbccddeeff?index=%2Fadmin%2Forders"
   class="btn btn-sm btn-outline-secondary polysource-filter-share"
   data-polysource-share-url="..."
   aria-label="Copy share link">
    Copy share link
</a>
```

The user right-clicks → "Copy link address", or the host wires
a 3-line clipboard controller hooked on the
`data-polysource-share-url` attribute.

## Resolving

When someone follows the short URL, `FilterUrlTokenController`
looks up the token, fetches the stored filter slice, and
redirects to `index` + `?filters[...]=...`. The original page
renders identically to a long URL.

## Token format

12 lowercase hex chars (`[a-f0-9]{12}`) — collision space 2^48.
The service retries on collision (extremely rare for the
volumes typical of admin UIs); after 8 retries it throws —
indicating either an exhausted name space (unrealistic) or a
broken random source (operational issue).

## Security

- Tokens are NOT scoped to a user — anyone with the token can
  resolve it. The destination URL is gated by the host's
  Symfony firewall as usual.
- The redirect target (`?index=`) MUST start with `/` — the
  controller refuses absolute external URLs to prevent
  open-redirect.

## Storage

```sql
CREATE TABLE polysource_filter_url_tokens (
    token VARCHAR(32) NOT NULL,
    resource_name VARCHAR(128) NOT NULL,
    filters_slice_json TEXT NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (token)
);
CREATE INDEX polysource_filter_url_tokens_resource_idx ON polysource_filter_url_tokens (resource_name);
CREATE INDEX polysource_filter_url_tokens_created_idx ON polysource_filter_url_tokens (created_at);
```

## Retention

Tokens never expire by default — they live as long as the
table row. Hosts who want TTLs prune via cron:

```sql
DELETE FROM polysource_filter_url_tokens WHERE created_at < NOW() - INTERVAL '30 days';
```

Followers of an expired token get a 404 (the controller's
behaviour for unknown tokens). Hosts who want a friendlier
"Link expired" page wrap the route in their own controller.

## API — minting / resolving from PHP

```php
use Polysource\Filter\FilterUrlToken\FilterUrlTokenService;

$token = $service->tokenize('orders', [
    'status' => ['value' => 'paid', 'comparison' => '='],
    'country' => ['FR', 'DE'],
]);

if (null !== $token) {
    echo $token->token; // 12-char hex string
}

$resolved = $service->resolve('aabbccddeeff');
if (null !== $resolved) {
    var_dump($resolved->filtersSlice);
}
```

## Why not just use a hashed URL?

A naive hash (e.g. `base64(filters)`) leaks the filter slice in
the URL — defeats the "short URL" goal AND prevents revocation
(can't expire a hash). A server-side mapping is needed for
both brevity and the option to expire / revoke later.
