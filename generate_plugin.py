import json
import re

# Load articles
with open('articles_full.json', 'r') as f:
    articles = json.load(f)

print(f"Loaded {len(articles)} articles")

# Group by folder
folders = {}
for a in articles:
    folder = a.get('folder', 'General') or 'General'
    if folder not in folders:
        folders[folder] = []
    folders[folder].append(a)

print(f"Found {len(folders)} folders")

# Generate PHP seed data
php_articles = []
for i, a in enumerate(articles):
    title = a['title'].replace("'", "\\'").replace("\\", "\\\\")
    slug = a['slug'].replace("'", "\\'")
    folder = (a.get('folder') or 'General').replace("'", "\\'")
    content = a.get('content', '').replace("'", "\\'").replace("\\", "\\\\")[:50000]
    images = a.get('images', [])[:10]
    videos = a.get('videos', [])[:5]
    
    images_php = "array('" + "','".join([img.replace("'", "\\'") for img in images]) + "')" if images else "array()"
    videos_php = "array('" + "','".join([vid.replace("'", "\\'") for vid in videos]) + "')" if videos else "array()"
    
    php_articles.append(f"""array(
        'title' => '{title}',
        'slug' => '{slug}',
        'folder' => '{folder}',
        'content' => '{content}',
        'images' => {images_php},
        'videos' => {videos_php}
    )""")

# Write to file
with open('plugin_articles_data.php', 'w') as f:
    f.write("<?php\n")
    f.write("// Auto-generated article data - " + str(len(articles)) + " articles\n")
    f.write("function get_kb_articles_data() {\n")
    f.write("    return array(\n")
    f.write(",\n".join(php_articles))
    f.write("\n    );\n")
    f.write("}\n")

print(f"Generated plugin_articles_data.php")
print(f"File contains {len(articles)} articles")
