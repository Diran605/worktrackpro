import zipfile
import xml.etree.ElementTree as ET
import sys
import os

sys.stdout.reconfigure(encoding='utf-8')

docx_path = os.path.join(os.path.dirname(__file__), 'WorkTrackPro_Feature_Addendum_v3.docx')
z = zipfile.ZipFile(docx_path)
tree = ET.parse(z.open('word/document.xml'))

ns = '{http://schemas.openxmlformats.org/wordprocessingml/2006/main}'

paragraphs = []
for p in tree.iter(f'{ns}p'):
    texts = []
    for t in p.iter(f'{ns}t'):
        if t.text:
            texts.append(t.text)
    line = ''.join(texts).strip()
    if line:
        paragraphs.append(line)

output = '\n'.join(paragraphs)
# Write to file instead of printing
out_path = os.path.join(os.path.dirname(__file__), 'feature_doc_text.txt')
with open(out_path, 'w', encoding='utf-8') as f:
    f.write(output)
print(f"Written {len(paragraphs)} paragraphs to {out_path}")
