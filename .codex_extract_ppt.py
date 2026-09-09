import zipfile,re,sys
from xml.etree import ElementTree as ET
sys.stdout.reconfigure(encoding='utf-8')
p='documents/iScan_CRDMS_System_Features_2026_v5.pptx'
ns={'a':'http://schemas.openxmlformats.org/drawingml/2006/main'}
with zipfile.ZipFile(p) as z:
    slides=sorted([n for n in z.namelist() if re.fullmatch(r'ppt/slides/slide\d+\.xml',n)], key=lambda n:int(re.search(r'\d+',n).group()))
    for i,n in enumerate(slides,1):
        root=ET.fromstring(z.read(n)); texts=[''.join(t.itertext()) for t in root.findall('.//a:t',ns)]
        print(f'--- {i} ---')
        print(' | '.join(t.strip() for t in texts if t.strip()))
