"""Runs against the actual optimized APK on an Android emulator; no submissions."""
import json, os, re, subprocess, sys, time, xml.etree.ElementTree as ET
from pathlib import Path
ADB=os.environ.get('ADB','adb')
PACKAGE='de.ruhrpottguru.testapp'
report=[]
def adb(*args):
    return subprocess.check_output([ADB,*args],text=True,stderr=subprocess.STDOUT,timeout=35)
def tree():
    adb('shell','uiautomator','dump','/sdcard/rpg-window.xml')
    return ET.fromstring(adb('shell','cat','/sdcard/rpg-window.xml'))
def lookup(root,value,attr='text'):
    return next((n for n in root.iter('node') if value in n.get(attr,'')),None)
def find(value,attr='text',scroll=False,timeout=25):
    end=time.monotonic()+timeout
    while time.monotonic()<end:
        root=tree();node=lookup(root,value,attr)
        if node is not None:return root,node
        if scroll:adb('shell','input','swipe','540','1800','540','750','350')
        time.sleep(.4)
    raise AssertionError('Not visible: '+value)
def click(value,attr='text',scroll=False):
    root,node=find(value,attr,scroll)
    nums=list(map(int,re.findall(r'\d+',node.get('bounds'))))
    adb('shell','input','tap',str((nums[0]+nums[2])//2),str((nums[1]+nums[3])//2))
    time.sleep(.4)
def check(value):find(value);report.append(value)
try:
    adb('shell','pm','clear',PACKAGE)
    adb('shell','am','start','-n',PACKAGE+'/.MainActivity')
    # First start must ask for location access, without tapping the crosshair.
    click('permission_allow_foreground_only_button','resource-id')
    report.append('Startup requests Android location permission')
    for _ in range(5):
        adb('emu','geo','fix','6.6186','51.6572');time.sleep(1)
    # Close a pending location timeout dialog if the emulator did not deliver a fresh fix.
    root=tree()
    if lookup(root,'Schließen') is not None:click('Schließen')
    click('Standort und Radius auswählen','content-desc')
    root,node=find('GPS')
    parents={child:parent for parent in root.iter() for child in parent}
    ancestry=[]
    while node is not None:
        ancestry.append(node);node=parents.get(node)
    assert any(n.get('selected')=='true' or n.get('checked')=='true' for n in ancestry),'GPS chip is not selected'
    report.append('Crosshair opens with GPS selected')
    click('Ort');check('PLZ oder Stadt auswählen')
    click('Schließen')
    click('Hauptmenü öffnen','content-desc');click('Specials & Mehr');click('Pott-Quiz')
    click('Quiz starten')
    questions=json.loads(Path('android-v0.16.1/QA/v0.16.1/quiz-web-original.json').read_text())['questions']
    for question in questions:
        answer=question['options'][question['correctIndex']]
        click(answer,scroll=True)
        click('Antwort einloggen',scroll=True)
        click('Ergebnis anzeigen' if question['id']==10 else 'Nächste Frage',scroll=True)
    check('Echter Ruhrpott Guru');check('10 / 10');check('100 %')
    report.append('All ten quiz questions answered; result and rank correct')
    click('Nochmal spielen');check('Frage 1 von 10')
    adb('shell','am','force-stop',PACKAGE)
    adb('shell','am','start','-n',PACKAGE+'/.MainActivity')
    time.sleep(3)
    root=tree()
    if lookup(root,'Schließen') is not None:click('Schließen')
    click('Hauptmenü öffnen','content-desc');click('Specials & Mehr');click('Pott-Quiz')
    check('Highscore: 100 %')
    report.append('Highscore persists after process restart')
    result={'result':'PASS','checks':report}
except Exception as exc:
    result={'result':'FAIL','checks':report,'error':str(exc)}
    Path('android-ui-test.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n')
    print(json.dumps(result,ensure_ascii=False));raise
Path('android-ui-test.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n')
print(json.dumps(result,ensure_ascii=False))
