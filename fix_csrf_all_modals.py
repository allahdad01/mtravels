#!/usr/bin/env python3
"""
Batch fix all modal files to add CSRF tokens
"""
import os
import re
import sys

modals_dir = 'modals'
csrf_pattern = r'<input[^>]*type="hidden"[^>]*name="csrf_token"[^>]*>'
form_pattern = r'<form[^>]*>'
csrf_token = '\n                    <!-- CSRF Protection -->\n                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION[\'csrf_token\'] ?? \'\'); ?>">'

stats = {
    'total': 0,
    'protected': 0,
    'fixed': 0,
    'skipped': 0,
    'errors': []
}

def fix_modal_file(filepath):
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
        
        # Skip if already has CSRF token
        if re.search(csrf_pattern, content, re.IGNORECASE):
            return 'protected'
        
        # Skip if no form tag
        if not re.search(form_pattern, content, re.IGNORECASE):
            return 'skipped'
        
        # Add CSRF token after first form tag
        new_content = re.sub(
            form_pattern,
            lambda m: m.group(0) + csrf_token,
            content,
            count=1,
            flags=re.IGNORECASE
        )
        
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(new_content)
        
        return 'fixed'
    except Exception as e:
        stats['errors'].append((filepath, str(e)))
        return 'error'

def main():
    print("Scanning modals directory...")
    for root, dirs, files in os.walk(modals_dir):
        for file in files:
            if file.endswith('.php'):
                filepath = os.path.join(root, file)
                stats['total'] += 1
                result = fix_modal_file(filepath)
                
                if result == 'protected':
                    stats['protected'] += 1
                    print(f"✓ {filepath}")
                elif result == 'fixed':
                    stats['fixed'] += 1
                    print(f"✓ Fixed: {filepath}")
                elif result == 'skipped':
                    stats['skipped'] += 1
                    print(f"- Skipped: {filepath}")
                elif result == 'error':
                    print(f"✗ Error: {filepath}")

    # Print summary
    print("\n" + "="*70)
    print("CSRF PROTECTION FIX SUMMARY")
    print("="*70)
    print(f"Total files: {stats['total']}")
    print(f"Already protected: {stats['protected']}")
    print(f"Fixed: {stats['fixed']}")
    print(f"Skipped (no forms): {stats['skipped']}")
    print(f"Errors: {len(stats['errors'])}")
    print("="*70)
    
    if stats['errors']:
        print("\nFiles with errors:")
        for filepath, error in stats['errors']:
            print(f"  {filepath}: {error}")
    
    print(f"\nDone! Fixed {stats['fixed']} modals with CSRF protection.")

if __name__ == '__main__':
    main()
