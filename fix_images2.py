import os, re

kb_path = 'oversee-support-tickets/knowledge-base'
updated = 0

for root, dirs, files in os.walk(kb_path):
    if 'images' in root or 's3.amazonaws' in root:
        continue
    for f in files:
        if not f.endswith('.html'):
            continue
        filepath = os.path.join(root, f)
        
        with open(filepath, 'r', encoding='utf-8') as file:
            content = file.read()
        
        original = content
        
        # Replace ../s3.amazonaws.com/.../filename.jpg?123 with ../images/filename.jpg
        def replace_url(match):
            full_path = match.group(1)
            # Get just the filename (before the ?)
            filename = full_path.split('/')[-1].split('?')[0]
            return f'src="../images/{filename}"'
        
        content = re.sub(r'src="(\.\./s3\.amazonaws\.com/[^"]+)"', replace_url, content)
        
        if content != original:
            with open(filepath, 'w', encoding='utf-8') as file:
                file.write(content)
            updated += 1
            print(f"Updated: {f}")

print(f"\nTotal updated: {updated} HTML files")
