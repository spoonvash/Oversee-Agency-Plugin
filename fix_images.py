import os, shutil, json, re

# Create simple images folder
img_dest = 'oversee-support-tickets/knowledge-base/images'
os.makedirs(img_dest, exist_ok=True)

# Copy all images with just filename
src = '/workspaces/OverseeCRM/Database/s3.amazonaws.com'
copied = 0
img_map = {}  # old URL -> new filename

for root, dirs, files in os.walk(src):
    for f in files:
        if f.endswith(('.jpg', '.png', '.gif', '.jpeg', '.webp')):
            src_path = os.path.join(root, f)
            # Keep original filename
            dest_path = os.path.join(img_dest, f)
            
            # Handle duplicates by adding number
            if os.path.exists(dest_path):
                name, ext = os.path.splitext(f)
                i = 1
                while os.path.exists(os.path.join(img_dest, f"{name}_{i}{ext}")):
                    i += 1
                f = f"{name}_{i}{ext}"
                dest_path = os.path.join(img_dest, f)
            
            shutil.copy2(src_path, dest_path)
            
            # Map old path to new filename
            old_url_part = root.replace('/workspaces/OverseeCRM/Database/', '')
            img_map[old_url_part + '/' + os.path.basename(src_path)] = f
            copied += 1

print(f"Copied {copied} images to /images/")

# Update HTML files
kb_path = 'oversee-support-tickets/knowledge-base'
updated = 0

for root, dirs, files in os.walk(kb_path):
    if 'images' in root:
        continue
    for f in files:
        if not f.endswith('.html'):
            continue
        filepath = os.path.join(root, f)
        
        with open(filepath, 'r', encoding='utf-8') as file:
            content = file.read()
        
        original = content
        
        # Replace all s3/cdn URLs with simple ../images/filename
        def replace_url(match):
            url = match.group(1)
            # Extract just the filename from the URL
            filename = url.split('/')[-1].split('?')[0]
            return f'src="../images/{filename}"'
        
        content = re.sub(r'src="https?://s3\.amazonaws\.com/[^"]+/([^"/?]+)(?:\?[^"]*)?"', replace_url, content)
        content = re.sub(r'src="\.\.\/\.\.\/\.\.\/\.\.\/s3\.amazonaws\.com/[^"]+/([^"/?]+)(?:\?[^"]*)?"', replace_url, content)
        content = re.sub(r'src="https?://s3\.amazonaws\.com/cdn\.freshdesk\.com/[^"]*?/([^"/?]+)(?:\?[^"]*)?"', replace_url, content)
        
        if content != original:
            with open(filepath, 'w', encoding='utf-8') as file:
                file.write(content)
            updated += 1

print(f"Updated {updated} HTML files")
print("Images now at: ../images/filename.jpg")
