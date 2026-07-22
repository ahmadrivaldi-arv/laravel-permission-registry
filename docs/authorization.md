# Laravel authorization integration

Registry permission names are ordinary Spatie/Laravel abilities. Existing Laravel authorization APIs continue to work.

## Global capabilities

Use full permission names for application-wide capabilities:

```php
$request->user()->can('products.create');

Gate::authorize('products.create');

$this->authorize('products.create');
```

Spatie registers permissions with Laravel Gate, so no package-specific authorization helper is required.

## Controllers

```php
public function store(StoreProductRequest $request): RedirectResponse
{
    $this->authorize('products.create');

    $product = Product::create($request->validated());

    return redirect()->route('products.show', $product);
}
```

Always authorize server-side even when the corresponding UI control is hidden.

## Livewire

```php
public function createProduct(): void
{
    $this->authorize('products.create');

    // Perform the authorized action.
}
```

For record-level access:

```php
public function updateProduct(Product $product): void
{
    $this->authorize('update', $product);

    // The policy evaluates ownership, tenant, state, or scope.
}
```

The package ships no Livewire component and has no dependency on Livewire.

## Blade

```blade
@can('products.view_any')
    <a href="{{ route('products.index') }}">Products</a>
@endcan

@can('products.create')
    <a href="{{ route('products.create') }}">Create product</a>
@endcan
```

Blade visibility is presentation behavior only. It is not a server-side security boundary.

## Policies and contextual rules

Global registry permissions answer questions such as:

- may this user create products?
- may this user browse reports?
- may this user run imports?

Policies remain the right place for contextual questions:

- does this user own this record?
- does this record belong to the current tenant?
- is this product in a state that may be published?
- may this user access this region or data scope?

Example policy:

```php
public function update(User $user, Product $product): bool
{
    return $user->can('products.update')
        && $user->tenant_id === $product->tenant_id
        && ! $product->is_locked;
}
```

Resources are not models, so the package does not discover models, map policies, or generate policies.

## Independent capabilities

Every permission is independent. Nothing in the registry grants a parent, related, or recommended permission.

```php
$this->authorize('users.assign_roles');

// This workflow may load role options for assignment.
// It does not need, imply, or grant roles.view_any.
```

After the check above:

```php
$user->can('users.assign_roles'); // may be true
$user->can('roles.view_any');     // remains false unless explicitly granted
```

This distinction lets a narrowly scoped workflow use the data it needs without granting a broader browsing capability.

## Recommendations do not authorize

If `products.publish` recommends `reports.view_any`, only the explicitly assigned permission affects access:

```php
$user->can('products.publish'); // true when assigned
$user->can('reports.view_any'); // independent result
```

Recommendations are metadata for inspection only.

## Server-side checklist

- Authorize every mutation and sensitive read on the server.
- Use full registry names for global capabilities.
- Use policies for record context.
- Do not rely on menu/button visibility.
- Do not infer one permission from another.
- Do not treat recommendations as requirements.
- Keep management operations behind their configured global abilities.
