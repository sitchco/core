# Module Examples

This directory contains example module implementations demonstrating common patterns and use cases.

## Available Examples

### 1. Simple Module
**Directory:** `simple-module/`

A minimal module example showing basic structure and asset management.

**Features:**
- Basic module structure
- Frontend asset enqueueing
- Simple WordPress hook usage

**Use when:** Creating a straightforward module with minimal dependencies.

---

### 2. Custom Post Type Module
**Directory:** `custom-post-type-module/`

A complete example showing custom post type registration with Timber integration.

**Features:**
- Custom post type registration
- Timber post class
- Repository pattern
- ACF field integration
- Admin customizations (feature flags)
- Asset management

**Use when:** Creating a module with a custom post type.

---

## Using These Examples

### Option 1: Copy and Modify

```bash
# Copy example to your theme's modules directory
cp -r examples/custom-post-type-module /path/to/theme/modules/MyFeature

# Rename files
cd /path/to/theme/modules/MyFeature
mv CustomPostTypeModule.php MyFeatureModule.php

# Update namespaces and class names in files
# Register in sitchco.config.php
```

### Option 2: Reference

Use these examples as a reference while building your own module from scratch.

## Example Structure Convention

Each example follows this structure:

```
example-name/
├── README.md              # Explanation of the example
├── ModuleFile.php         # Main module class
├── SupportingClass.php    # Additional classes (if applicable)
└── config-example.php     # Example sitchco.config.php entry
```

## Additional Resources

- [Creating a Module Guide](../guides/creating-a-module.md)
- [Base Module API Reference](../reference/base-module-api.md)
- [Architecture Overview](../architecture/overview.md)
