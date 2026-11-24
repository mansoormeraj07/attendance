(function(){
 var NAME=(document.title.split(' - ').pop()||'this app').trim(),KEY='pwa_x',dp=null;
 function ls(a,k,v){try{return a==='g'?localStorage.getItem(k):localStorage.setItem(k,v)}catch(e){return null}}
 var standalone=matchMedia('(display-mode: standalone)').matches||navigator.standalone===true;
 if('serviceWorker' in navigator&&window.isSecureContext)navigator.serviceWorker.register('sw.js').catch(function(){});
 if(standalone)return;
 var st=document.createElement('style');st.textContent='#pwa{position:fixed;right:max(16px,env(safe-area-inset-right,0px));bottom:max(16px,env(safe-area-inset-bottom,0px));z-index:80;width:min(380px,calc(100vw - 32px));background:rgba(14,21,48,.92);backdrop-filter:blur(14px);border:1px solid rgba(91,140,255,.45);border-radius:18px;box-shadow:0 20px 60px rgba(0,0,0,.55),0 0 0 4px rgba(91,140,255,.1);padding:16px;color:#e8ecf8;font:14px/1.45 system-ui,sans-serif;animation:pwai .5s both}#pwa[hidden]{display:none}#pwa h4{margin:0 0 4px;font-size:15px}#pwa p{margin:0 0 12px;color:#aab6d2}#pwa .r{display:flex;gap:8px;align-items:center}#pwa button{font:inherit;border-radius:10px;padding:8px 14px;cursor:pointer;border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.06);color:#e8ecf8}#pwa button.go{border:0;color:#fff;font-weight:600;background:linear-gradient(135deg,#5b8cff,#8a5bff);box-shadow:0 0 18px rgba(91,140,255,.4)}#pwa img{width:38px;height:38px;border-radius:10px;float:left;margin:0 12px 6px 0}@keyframes pwai{from{opacity:.001;transform:translateY(14px)}to{opacity:1;transform:none}}@media(prefers-reduced-motion:reduce){#pwa{animation:none}}';document.head.appendChild(st);
 var box=document.createElement('div');box.id='pwa';box.hidden=true;box.setAttribute('role','dialog');box.setAttribute('aria-label','Install app');document.body.appendChild(box);
 function show(html){box.innerHTML='<img src="assets/icons/icon-192.png" alt="">'+html;box.hidden=false}
 function later(){box.hidden=true;ls('s',KEY,String(Date.now()+14*864e5))}
 function snoozed(){var t=+ls('g',KEY);return t&&t>Date.now()}
 addEventListener('beforeinstallprompt',function(e){e.preventDefault();dp=e;if(snoozed())return;
  show('<h4>Install '+NAME+' on your computer</h4><p>Open it like a normal app from your desktop or Start menu, in its own clean window. Same login, same data.</p><div class="r"><button class="go" id="pwago" type="button">Install app</button><button id="pwano" type="button">Not now</button></div>');
  document.getElementById('pwano').onclick=later;
  document.getElementById('pwago').onclick=function(){box.hidden=true;dp.prompt();dp.userChoice.then(function(c){if(c.outcome!=='accepted')ls('s',KEY,String(Date.now()+3*864e5))});dp=null}});
 addEventListener('appinstalled',function(){show('<h4>Installed. Thank you!</h4><p>Open '+NAME+' from your desktop or Start menu. If you do not see the icon yet, restart your browser (or your computer) once.</p><div class="r"><button class="go" id="pwaok" type="button">Got it</button></div>');document.getElementById('pwaok').onclick=function(){box.hidden=true}});
 var ios=/iphone|ipad|ipod/i.test(navigator.userAgent);
 if(ios&&!snoozed())setTimeout(function(){show('<h4>Add '+NAME+' to your Home Screen</h4><p>Tap the Share button, then "Add to Home Screen" to use it like an app.</p><div class="r"><button id="pwano" type="button">Not now</button></div>');document.getElementById('pwano').onclick=later},2500);
})();
