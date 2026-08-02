@if ($errors->any())
    <div class="alert alert-error">
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif

<div class="form-group">
    <label for="name" class="form-label">Product Name</label>
    <input id="name" type="text" name="name" value="{{ old('name', $product->name ?? '') }}" required class="form-control">
    @error('name') <span class="field-error">{{ $message }}</span> @enderror
</div>

<div class="form-group">
    <label for="category_id" class="form-label">Category</label>
    <select id="category_id" name="category_id" required class="form-control" data-searchable-select>
        <option value="">Select a category</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id ?? '') == $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
    @error('category_id') <span class="field-error">{{ $message }}</span> @enderror
</div>

<div class="form-group">
    <label for="description" class="form-label">Description</label>
    <textarea id="description" name="description" rows="3" class="form-control" data-rich-text>{{ old('description', $product->description ?? '') }}</textarea>
    @error('description') <span class="field-error">{{ $message }}</span> @enderror
</div>

<div class="form-row">
    <div class="form-group">
        <label for="mrp" class="form-label">MRP (₹)</label>
        <input id="mrp" type="number" name="mrp" value="{{ old('mrp', $product->mrp ?? 0) }}" min="0" step="0.01" required class="form-control">
        @error('mrp') <span class="field-error">{{ $message }}</span> @enderror
    </div>

    <div class="form-group">
        <label for="sale_price" class="form-label">Sale Price (₹)</label>
        <input id="sale_price" type="number" name="sale_price" value="{{ old('sale_price', $product->sale_price ?? 0) }}" min="0" step="0.01" required class="form-control">
        @error('sale_price') <span class="field-error">{{ $message }}</span> @enderror
        <small style="color: var(--text-muted);">Must not exceed MRP.</small>
    </div>

    <div class="form-group">
        <label for="stock" class="form-label">Stock</label>
        <input id="stock" type="number" name="stock" value="{{ old('stock', $product->stock ?? 0) }}" min="0" required class="form-control">
        @error('stock') <span class="field-error">{{ $message }}</span> @enderror
    </div>
</div>

<div class="form-group">
    <label for="priority" class="form-label">Priority</label>
    <input id="priority" type="number" name="priority" value="{{ old('priority', $product->priority ?? 0) }}" min="0" class="form-control">
    <small style="color: var(--text-muted);">Lower numbers appear first on the website.</small>
    @error('priority') <span class="field-error">{{ $message }}</span> @enderror
</div>

<div class="form-group">
    <label class="form-label">Thumbnail Image</label>
    <input id="thumbnail" type="file" name="thumbnail" accept="image/*" class="form-control">
    <small style="color: var(--text-muted);">PNG, JPG up to 2MB</small>
    @error('thumbnail') <span class="field-error">{{ $message }}</span> @enderror
    <img id="thumbnail-preview" src="{{ isset($product) && $product->thumbnail ? asset('storage/'.$product->thumbnail) : '' }}"
         alt="" class="thumb-lg form-preview" style="{{ isset($product) && $product->thumbnail ? '' : 'display: none;' }}">
</div>

<div class="form-group">
    <label class="form-label">Product Images</label>
    <input id="images" type="file" name="images[]" accept="image/*" multiple class="form-control">
    <small style="color: var(--text-muted);">You can select multiple images</small>
    @error('images') <span class="field-error">{{ $message }}</span> @enderror
    <div class="gallery-grid" id="new-images-preview"></div>

    @if (isset($product) && $product->images->isNotEmpty())
        <div class="gallery-grid" id="existing-images">
            @foreach ($product->images as $image)
                <div class="gallery-item">
                    <img src="{{ asset('storage/'.$image->image) }}" alt="">
                    <button type="button" class="gallery-item-remove"
                            data-image-id="{{ $image->id }}"
                            data-product-id="{{ $product->id }}"
                            data-url="{{ route('admin.products.images.destroy', [$product, $image]) }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="M6 6l12 12"/></svg>
                    </button>
                </div>
            @endforeach
        </div>
    @endif
</div>

<div class="form-group">
    <label for="related_products" class="form-label">Related Products</label>
    <select id="related_products" name="related_products[]" multiple data-searchable-select>
        @php
            $selectedRelatedIds = collect(old('related_products', isset($product) ? $product->relatedProducts->pluck('id')->all() : []));
        @endphp
        @foreach ($allProducts as $productOption)
            @continue(isset($product) && $productOption->id === $product->id)
            <option value="{{ $productOption->id }}" @selected($selectedRelatedIds->contains((string) $productOption->id) || $selectedRelatedIds->contains($productOption->id))>
                {{ $productOption->name }}
            </option>
        @endforeach
    </select>
</div>

<div class="form-group">
    <label class="form-label">Specifications</label>

    @php
        if (old('specification_keys')) {
            $specRows = collect(old('specification_keys'))
                ->map(fn ($key, $i) => ['key' => $key, 'value' => old('specification_values')[$i] ?? '']);
        } else {
            $specRows = isset($product)
                ? $product->specifications->map(fn ($spec) => ['key' => $spec->key, 'value' => $spec->value])
                : collect();
        }
    @endphp

    <div id="specification-rows">
        @foreach ($specRows as $spec)
            <div class="spec-row">
                <input type="text" name="specification_keys[]" value="{{ $spec['key'] }}" placeholder="e.g. Material" class="form-control">
                <input type="text" name="specification_values[]" value="{{ $spec['value'] }}" placeholder="e.g. Cotton" class="form-control">
                <button type="button" class="btn-icon danger" data-action="remove-spec-row" title="Remove">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="M6 6l12 12"/></svg>
                </button>
            </div>
        @endforeach
    </div>

    <button type="button" class="btn btn-secondary" id="add-spec-row">+ Add Specification</button>

    <template id="spec-row-template">
        <div class="spec-row">
            <input type="text" name="specification_keys[]" placeholder="e.g. Material" class="form-control">
            <input type="text" name="specification_values[]" placeholder="e.g. Cotton" class="form-control">
            <button type="button" class="btn-icon danger" data-action="remove-spec-row" title="Remove">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="M6 6l12 12"/></svg>
            </button>
        </div>
    </template>
</div>

<div class="form-group">
    <label for="status" class="form-label">Status</label>
    <select id="status" name="status" class="form-control">
        <option value="1" @selected(old('status', $product->status ?? true) == '1')>Active</option>
        <option value="0" @selected(old('status', $product->status ?? true) == '0')>Inactive</option>
    </select>
</div>
