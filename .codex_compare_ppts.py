import zipfile,re,sys
from xml.etree import ElementTree as ET
sys.stdout.reconfigure(encoding='utf-8')
for p in ['documents/iScan_Executive_Presentation_v2.pptx','documents/iScan_CRDMS_System_Features_2026_v5.pptx']:
 print('\n###',p)
 with zipfile.ZipFile(p) as z:
  slides=sorted([n for n in z.namelist() if re.fullmatch(r'ppt/slides/slide\d+\.xml',n)], key=lambda n:int(re.search(r'\d+',n).group()))
  print('slides',len(slides))
  ns={'a':'http://schemas.openxmlformats.org/drawingml/2006/main'}
  for i,n in enumerate(slides,1):
   root=ET.fromstring(z.read(n)); t=[''.join(e.itertext()).strip() for e in root.findall('.//a:t',ns)]
   print(i,' | '.join(x for x in t[:5] if x))
