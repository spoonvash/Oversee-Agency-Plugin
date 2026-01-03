import os, re, json

articles = []
base = "Database/help.leadconnectorhq.com/support/solutions/articles"

for root, dirs, files in os.walk(base):
    for f in files:
        if f.endswith(".html"):
            path = os.path.join(root, f)
            try:
                with open(path, 'r', encoding='utf-8', errors='ignore') as file:
                    html = file.read()
                
                # Better title extraction
                title = ""
                # Try h2 with article-title class
                t = re.search(r'<h2[^>]*class="[^"]*article-title[^"]*"[^>]*>([^<]+)</h2>', html)
                if t: title = t.group(1).strip()
                # Try first h2
                if not title:
                    t = re.search(r'<h2[^>]*>([^<]+)</h2>', html)
                    if t: title = t.group(1).strip()
                # Try h1
                if not title or title == "LeadConnector":
                    t = re.search(r'<h1[^>]*>([^<]+)</h1>', html)
                    if t and t.group(1).strip() != "LeadConnector": 
                        title = t.group(1).strip()
                # Try title tag
                if not title or title == "LeadConnector":
                    t = re.search(r'<title>([^<]+)</title>', html)
                    if t: 
                        title = t.group(1).replace(" : LeadConnector","").strip()
                
                # Get content
                content = ""
                c = re.search(r'<article[^>]*>(.*?)</article>', html, re.DOTALL)
                if c: content = c.group(1)
                if not content:
                    c = re.search(r'class="article-body"[^>]*>(.*?)</div>', html, re.DOTALL)
                    if c: content = c.group(1)
                
                images = re.findall(r'<img[^>]+src="([^"]*(?:freshdesk|amazonaws|tango)[^"]*)"', html)
                videos = re.findall(r'https?://[^"<>\s]*(?:youtube\.com/embed|youtu\.be|vimeo\.com|loom\.com/share|wistia)[^"<>\s]*', html)
                
                # Get folder info
                folder = ""
                fm = re.search(r'href="[^"]*folders/(\d+)[^"]*"[^>]*>([^<]+)</a>', html)
                if fm: folder = fm.group(2).strip()
                
                if title and title != "LeadConnector":
                    articles.append({
                        "slug": f.replace(".html",""),
                        "title": title,
                        "folder": folder,
                        "images": images[:15],
                        "videos": list(set(videos))[:5],
                        "content": content[:80000]
                    })
            except Exception as e:
                pass

print(f"Extracted {len(articles)} articles")
with open("articles_full.json", "w") as f:
    json.dump(articles, f)
print("Saved to articles_full.json")
