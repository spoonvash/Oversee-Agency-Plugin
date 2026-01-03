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
                
                title = ""
                t = re.search(r'<h1[^>]*>([^<]+)</h1>', html)
                if t: title = t.group(1).strip()
                if not title:
                    t = re.search(r'<title>([^<]+)</title>', html)
                    if t: title = t.group(1).replace(" : LeadConnector","").strip()
                
                content = ""
                c = re.search(r'<article[^>]*>(.*?)</article>', html, re.DOTALL)
                if c: content = c.group(1)
                
                images = re.findall(r'<img[^>]+src="([^"]*(?:freshdesk|amazonaws|tango)[^"]*)"', html)
                videos = re.findall(r'(?:youtube\.com/embed|youtu\.be|vimeo\.com|loom\.com/share)[^\s"<>]+', html)
                
                if title:
                    articles.append({"slug": f.replace(".html",""), "title": title, "images": images[:10], "videos": videos[:5], "content_length": len(content)})
            except: pass

print(json.dumps(articles, indent=2))
