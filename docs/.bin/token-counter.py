#!/usr/bin/env -S uv run --script

# /// script
# dependencies = [
#   "tiktoken",
# ]
# ///

import os
import sys
import tiktoken
from pathlib import Path

def count_tokens(text, encoding_name="cl100k_base"):
    """Count tokens using tiktoken for accurate results."""
    try:
        encoding = tiktoken.get_encoding(encoding_name)
        return len(encoding.encode(text))
    except Exception:
        # Fallback to rough estimation
        return len(text) // 4

def count_file_tokens(file_path):
    """Count tokens for a single file."""
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()
            tokens = count_tokens(content)
            chars = len(content)
            return {
                'file': str(file_path),
                'tokens': tokens,
                'chars': chars
            }
    except Exception as e:
        print(f"Error reading {file_path}: {e}")
        return None

def scan_directory(dir_path):
    """Scan directory for markdown files and count tokens."""
    results = []
    total_tokens = 0
    total_chars = 0

    for file_path in Path(dir_path).rglob("*.md"):
        try:
            with open(file_path, 'r', encoding='utf-8') as f:
                content = f.read()
                tokens = count_tokens(content)
                chars = len(content)
                relative_path = str(file_path.relative_to(dir_path))

                results.append({
                    'file': relative_path,
                    'tokens': tokens,
                    'chars': chars
                })

                total_tokens += tokens
                total_chars += chars
        except Exception as e:
            print(f"Error reading {file_path}: {e}")

    # Sort by token count descending
    results.sort(key=lambda x: x['tokens'], reverse=True)

    return results, total_tokens, total_chars

def main():
    # Get path from command line argument or use current directory
    if len(sys.argv) > 1:
        target_path = Path(sys.argv[1])
    else:
        target_path = Path.cwd()

    if not target_path.exists():
        print(f"Error: {target_path} does not exist")
        sys.exit(1)

    # Handle file vs directory
    if target_path.is_file():
        # Single file mode
        result = count_file_tokens(target_path)
        if result:
            print(f"Token count for {result['file']}:")
            print("=" * 60)
            print(f"{'Tokens':<20} {result['tokens']:>10}")
            print(f"{'Characters':<20} {result['chars']:>10}")
    elif target_path.is_dir():
        # Directory mode - scan for markdown files
        results, total_tokens, total_chars = scan_directory(target_path)

        if not results:
            print(f"No markdown files found in {target_path}")
            sys.exit(0)

        print(f"Token count for markdown files in {target_path}:")
        print("=" * 60)
        print(f"{'File':<40} {'Tokens':>10} {'Chars':>10}")
        print("-" * 60)

        for result in results:
            print(f"{result['file']:<40} {result['tokens']:>10} {result['chars']:>10}")

        print("-" * 60)
        print(f"{'TOTAL':<40} {total_tokens:>10} {total_chars:>10}")
    else:
        print(f"Error: {target_path} is neither a file nor directory")
        sys.exit(1)

if __name__ == "__main__":
    main()
