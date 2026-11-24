/* Attendance dashboard. Globals BR, USER, CSRF, CFG, RAW, SRC come from index.php */
const $=s=>document.querySelector(s);
const RM=matchMedia('(prefers-reduced-motion: reduce)').matches;
const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const NM={m:'Morning',e:'Evening',o:'Other'};
const P={m:{n:'Morning',s:540,e:1080},e:{n:'Evening',s:900,e:1560},grace:10,tol:120,ot:30,off:0};
const LSK='sd_cfg_'+USER.user;
function applyCfg(c){P.m.s=c.m.s;P.m.e=c.m.e;P.e.s=c.e.s;P.e.e=c.e.e;P.grace=c.grace;P.ot=c.ot;P.tol=c.tol;P.off=c.off}
applyCfg(CFG);
if(USER.role==='customer'){try{const o=JSON.parse(localStorage.getItem(LSK)||'null');if(o&&o.m&&o.e)applyCfg(o)}catch(e){}}
const ASGK='sd_asg_'+USER.user,CANASG=USER.role==='admin'||USER.role==='customer';
let ASG=Object.assign({},ASG0);
if(USER.role==='customer'){try{ASG=JSON.parse(localStorage.getItem(ASGK)||'{}')||{}}catch(e){ASG={}}}
let ses=[],dates=[],depts=[],SK=null,ROWS=[],LOCAL=false;
const F={shift:'all',dept:'all',from:null,to:null,q:'',sort:'late',open:null,n:40};

/* ---------- helpers ---------- */
const hm=t=>{const m=String(t==null?'':t).match(/(\d{1,2}):(\d{2})/);return m?+m[1]*60+ +m[2]:null};
const dn=s=>{const m=String(s).match(/^(\d{4})-(\d{2})-(\d{2})/);return m?Date.UTC(+m[1],m[2]-1,+m[3])/864e5:null};
const dd=n=>new Date(n*864e5),iso=n=>dd(n).toISOString().slice(0,10);
const dl=n=>dd(n).toLocaleDateString('en-GB',{weekday:'short',day:'numeric',month:'short',timeZone:'UTC'});
const dlong=n=>dd(n).toLocaleDateString('en-GB',{weekday:'short',day:'numeric',month:'short',year:'numeric',timeZone:'UTC'}).replace(',','');
const t12=m=>{m=((m%1440)+1440)%1440;const h=m/60|0;return ((h+11)%12+1)+':'+String(m%60).padStart(2,'0')+' '+(h<12?'AM':'PM')};
const hs=m=>(m/60).toFixed(1)+'h';
const mn=m=>m>=60?(m/60|0)+'h '+(m%60)+'m':m+'m';
const fm=m=>{m=((m%1440)+1440)%1440;return String(m/60|0).padStart(2,'0')+':'+String(m%60).padStart(2,'0')};
const rateOf=q=>q.n+q.abs?q.n/(q.n+q.abs)*100:null;
const rtxt=q=>{const r=rateOf(q);return r==null?'-':(Math.round(r*10)/10)+'%'};
let DR=null,lastFocus=null;
let toastT;function toast(t,ms,act){const e=$('#toast');e.textContent=t;if(act){const b=document.createElement('button');b.type='button';b.className='ta';b.textContent=act.label;b.onclick=()=>{e.hidden=true;act.fn()};e.appendChild(b)}e.hidden=false;clearTimeout(toastT);toastT=setTimeout(()=>e.hidden=true,act?7000:(ms||3200))}

/* ---------- sessions ---------- */
function mkF(e,a,b,k,d){const S=P[k],st=d*1440+S.s,en=d*1440+S.e;
 const s={e,a,b,k,d,h:b?(b-a)/60:0,late:a-st>P.grace?a-st:0,early:0,ot:0};
 if(b){s.early=en-b>P.grace?en-b:0;s.ot=b-en>=P.ot?b-en:0}return s}
function mkO(e,p,k,d){const s={e,a:p,b:p,k,d,h:0,late:0,early:0,ot:0,noin:1};
 if(k!='o'){const en=d*1440+P[k].e;s.early=en-p>P.grace?en-p:0;s.ot=p-en>=P.ot?p-en:0}return s}
function mk(e,a,b){
 const t=a%1440,day=a-t;let k='o',bd=1e9;
 for(const x of['m','e']){const d=Math.abs(t-P[x].s);if(d<bd&&d<=P.tol){bd=d;k=x}}
 const s={e,a,b,k,d:day/1440,h:b?(b-a)/60:0,late:0,early:0,ot:0};
 if(k!='o'){const S=P[k];s.late=t-S.s>P.grace?t-S.s:0;
  if(b){const end=day+S.e;s.early=end-b>P.grace?end-b:0;s.ot=b-end>=P.ot?b-end:0}}
 return s}

function build(){
 const by=new Map();
 for(const r of RAW){const d=dn(r[3]),a=hm(r[4]),b=hm(r[5]);if(d==null||a==null)continue;
  let e=by.get(r[0]);if(!e)by.set(r[0],e={id:r[0],name:String(r[1]).replace(/\s+/g,' ').trim(),dept:r[2],p:new Set()});
  e.p.add(d*1440+a);if(b!=null)e.p.add(d*1440+b)}
 let dmin=1e9,dmax=-1e9;for(const r of RAW){const x=dn(r[3]);if(x!=null){if(x<dmin)dmin=x;if(x>dmax)dmax=x}}
 const med=a=>{const z=a.slice().sort((x,y)=>x-y);return z[z.length>>1]};
 ses=[];const cov=new Map(),first=new Map(),mine=new Map(),fixed=new Map();
 for(const e of by.values()){
  const ps=[...e.p].sort((x,y)=>x-y),cv=new Set(),fk=ASG[String(e.id)];let my=[];
  if(fk==='m'||fk==='e'){
   /* shift chosen by the user: every punch goes to the day whose shift is nearest, first = in, last = out */
   fixed.set(e.id,fk);const S=P[fk],C=(S.s+S.e)/2,g=new Map();
   for(const t of ps){const d=Math.round((t-C)/1440);if(!g.has(d))g.set(d,[]);g.get(d).push(t)}
   for(const [d,pts] of [...g].sort((x,y)=>x[0]-y[0])){
    if(d<dmin||d>dmax)continue;
    const a=pts[0],b=pts[pts.length-1];
    if(b-a>=30)my.push(mkF(e,a,b,fk,d));
    else if(a<d*1440+C){const s=mkF(e,a,null,fk,d);if(S.e>1440&&d==dmax)s.open=1;my.push(s)}
    else my.push(mkO(e,a,fk,d))}
   first.set(e.id,my.length?my.reduce((m,s)=>Math.min(m,s.d),1e9):dmax+1);
  }else{
   first.set(e.id,Math.floor(ps[0]/1440));const lones=[];
   for(let i=0;i<ps.length;){const a=ps[i];
    if(a%1440<300){cv.add(Math.floor(a/1440)-1);i++;continue}
    const b=ps[i+1],near=b!==undefined&&b-a<30,gap=b!==undefined?Math.floor(b/1440)-Math.floor(a/1440):9;
    /* an out punch is only paired across midnight when it is a night-time punch (before 5 AM) */
    const fit=b!==undefined&&!near&&b-a<=960&&(gap==0||(gap==1&&a%1440>=720&&b%1440<300));
    if(fit)my.push(mk(e,a,b));else lones.push(a);
    i+=(fit||near)?2:1}
   const full=my.filter(s=>s.b&&Math.floor(s.b/1440)===Math.floor(s.a/1440));
   const c={m:0,e:0,o:0};my.forEach(s=>c[s.k]++);const dom=Object.keys(c).sort((x,y)=>c[y]-c[x])[0];
   let iT=null,oT=null;if(full.length>=3){iT=med(full.map(s=>s.a%1440));oT=med(full.map(s=>s.b%1440))}
   /* a lone punch close to this person's usual out time is an out punch with the in punch missing */
   for(const a of lones){const t=a%1440;
    if(iT!=null&&oT>iT&&Math.abs(t-oT)<Math.abs(t-iT))my.push(mkO(e,a,dom,Math.floor(a/1440)));
    else my.push(mk(e,a,null))}}
  my.forEach(s=>ses.push(s));cov.set(e.id,cv);mine.set(e.id,my)}
 dates=[...new Set(ses.map(s=>s.d))].sort((a,b)=>a-b);
 const cnt={};ses.forEach(s=>cnt[s.d]=(cnt[s.d]||0)+1);
 const L=dates[dates.length-1],pv=dates[dates.length-2];
 SK=dates.length>2&&cnt[L]<.7*cnt[pv]?L:null;
 for(const e of by.values()){const my=mine.get(e.id),have=new Set(my.map(s=>s.d)),c={m:0,e:0,o:0};my.forEach(s=>c[s.k]++);
  const k=fixed.get(e.id)||Object.keys(c).sort((x,y)=>c[y]-c[x])[0]||'o';
  for(const d of dates){if(d<first.get(e.id)||d==SK||have.has(d)||cov.get(e.id).has(d)||dd(d).getUTCDay()==P.off)continue;
   ses.push({e,a:d*1440,b:null,k,d,h:0,late:0,early:0,ot:0,abs:1})}}
 depts=[...new Set(ses.map(s=>s.e.dept))].sort();
 if(F.from!=null&&dates.length&&(F.from>dates[dates.length-1]||F.to<dates[0]))F.from=F.to=null;
 if(F.dept!='all'&&!depts.includes(F.dept))F.dept='all';
 const o=(v,l,sel)=>`<option value="${esc(v)}"${sel?' selected':''}>${esc(l)}</option>`;
 $('#dept').innerHTML=o('all','All departments',F.dept=='all')+depts.map(d=>o(d,d,F.dept==d)).join('');
 ['#dfrom','#dto'].forEach(i=>{$(i).min=dates.length?iso(dates[0]):'';$(i).max=dates.length?iso(dates[dates.length-1]):''});
 const n=new Set(ses.map(s=>s.e.id)).size;
 $('#sub').textContent=dates.length?`${SRC||'Saved data'} | ${dl(dates[0])} to ${dl(L)} | ${n} employees${SK?' | '+dl(SK)+' not counted for absence (day not finished)':''}`:'Attendance dashboard';
}

/* ---------- filtering / stats ---------- */
const hit=s=>!F.q||(s.e.name+' '+s.e.id).toLowerCase().includes(F.q);
const inRange=s=>F.from==null||(s.d>=F.from&&s.d<=F.to);
const fl=all=>ses.filter(s=>(all||F.shift=='all'||s.k==F.shift)&&(F.dept=='all'||s.e.dept==F.dept)&&inRange(s)&&hit(s));
function stats(l){const pr=l.filter(s=>!s.abs),done=pr.filter(s=>s.b&&!s.noin);
 return{people:new Set(l.map(s=>s.e.id)).size,n:pr.length,abs:l.length-pr.length,tot:done.reduce((a,s)=>a+s.h,0),
 avg:done.length?done.reduce((a,s)=>a+s.h,0)/done.length:0,late:pr.filter(s=>s.late).length,
 ot:pr.reduce((a,s)=>a+s.ot,0),miss:pr.filter(s=>(!s.b||s.noin)&&!s.open).length}}
function agg(l){const m=new Map();for(const s of l){if(!m.has(s.e.id))m.set(s.e.id,{e:s.e,l:[]});m.get(s.e.id).l.push(s)}
 return[...m.values()].map(r=>{r.q=stats(r.l);const c={m:0,e:0,o:0};r.l.forEach(s=>c[s.k]++);r.k=Object.keys(c).sort((a,b)=>c[b]-c[a])[0];return r})}
function statusText(s){if(s.abs)return'Absent';const p=[];if(s.noin)p.push('No in punch');else if(s.open)p.push('Out punch not in file yet');else if(!s.b)p.push('No out punch');
 if(s.late)p.push('Late '+mn(s.late));if(s.early)p.push('Left '+mn(s.early)+' early');if(s.ot)p.push('OT '+mn(s.ot));
 return p.length?p.join(', '):(s.k=='o'?'Other timing':'On time')}
const tone=s=>s.abs||s.noin||(!s.b&&!s.open)||s.late||s.early?'bad':(s.ot||s.k!='o'?'good':'mut');
const inS=s=>s.noin?'<span class="bad">no in punch</span>':t12(s.a);
const outS=s=>s.open?'<span class="sub2">out not in file yet</span>':(s.b?t12(s.b):'<span class="bad">no out punch</span>');

/* ---------- rendering ---------- */
function ruler(){
 const X0=24,SW=672,x=h=>Math.max(0,Math.min(720,X0+(h-6)*(SW/24)));let g='';
 for(let h=6;h<=30;h+=3)g+=`<line x1="${x(h)}" x2="${x(h)}" y1="22" y2="78" stroke="var(--line)"/><text x="${x(h)}" y="14" text-anchor="middle">${t12(h*60).replace(':00','')}</text>`;
 const bar=(k,y,fill,txt)=>{const S=P[k],a=S.s/60,b=S.e/60;
  return `<rect x="${x(a)}" y="${y}" width="${Math.max(2,x(b)-x(a))}" height="24" rx="6" fill="${fill}"/><text x="${x(a)+8}" y="${y+16}" style="fill:${txt};font-weight:600;font-size:11px">${S.n} ${t12(S.s).replace(':00','')} - ${t12(S.e).replace(':00','')}</text>`};
 const os=Math.max(P.m.s,P.e.s)/60,oe=Math.min(P.m.e,P.e.e)/60;
 g+=bar('m',26,'var(--m)','#1b1300')+bar('e',54,'var(--e)','var(--onE)');
 if(oe>os)g+=`<rect x="${x(os)}" y="23" width="${x(oe)-x(os)}" height="58" rx="6" fill="none" stroke="var(--ink)" stroke-dasharray="3 3"/><text x="${x((os+oe)/2)}" y="94" text-anchor="middle">Both shifts on site</text>`;
 $('#ruler').innerHTML=g}

function ring(r){const C=2*Math.PI*23,rc=r==null?'var(--mut)':r>=95?'var(--good)':r>=85?'var(--warn)':'var(--bad)';
 return `<svg class="ring" viewBox="0 0 58 58" aria-hidden="true"><circle class="bg" cx="29" cy="29" r="23"/><circle class="rgd" style="--C:${C}" cx="29" cy="29" r="23" stroke="${rc}" stroke-linecap="round" stroke-dasharray="${C}" stroke-dashoffset="${C*(1-(r||0)/100)}" transform="rotate(-90 29 29)"/><text x="29" y="33.5" text-anchor="middle">${r==null?'-':Math.round(r)}</text></svg>`}

function render(){
 const has=ses.length>0;
 $('#empty').hidden=has;$('#app').hidden=!has;
 const bn=$('#banner');bn.hidden=!LOCAL;
 if(LOCAL)$('#bantxt').innerHTML=`<b>${esc(SRC)}</b> | ${RAW.length} records. `+(USER.role==='customer'?'Private view: your file is processed in your browser and is not stored on our server.':(USER.save?'Not saved yet. Save it to make it the dashboard for everyone.':'Only you can see this file.'));
 if(!has){$('#emptyh').textContent=USER.role==='customer'?'Upload your attendance file':'No data to show yet';return}
 const l=fl(),s=stats(l),L=dates[dates.length-1],r=rateOf(s);
 $('#dfrom').value=F.from==null?'':iso(F.from);$('#dto').value=F.to==null?'':iso(F.to);
 $('#range').innerHTML=[[7,'Last 7 days'],[14,'Last 14 days'],[30,'Last 30 days'],[0,'All dates']].map(([n,t])=>`<button data-r="${n}" aria-pressed="${n?(F.to==L&&F.from==L-n+1):F.from==null}">${t}</button>`).join('');
 $('#segShift').innerHTML=[['all','All'],['m','Morning'],['e','Evening'],['o','Other']].map(([k,t])=>`<button data-s="${k}" aria-pressed="${F.shift==k}">${t}</button>`).join('');
 const kp=(v,l,c)=>`<div class="kpi ${c||''}"><div><b>${v}</b><span>${l}</span></div></div>`;
 $('#kpis').innerHTML=`<div class="kpi">${ring(r)}<div><b>${rtxt(s)}</b><span>Attendance</span></div></div>`+
  kp(s.n,'Present days')+kp(s.abs,'Absent days',s.abs?'bad':'')+kp(s.late,'Late arrivals',s.late?'bad':'')+kp(s.miss,'Missing punch',s.miss?'bad':'')+kp(hs(s.ot),'Overtime','good');
 const all=fl(true);
 $('#cards').innerHTML=['m','e','o'].map(k=>{const q=stats(all.filter(x=>x.k==k));
  const nm=NM[k],tm=k=='o'?'outside both shifts':t12(P[k].s)+' - '+t12(P[k].e);
  return `<div class="card sc ${k}${F.shift==k?' on':''}" data-s="${k}" tabindex="0" role="button" aria-label="Filter ${nm}"><h3>${nm}<small>${tm}</small></h3><div class="st">
  <div><b>${q.people}</b><span>People</span></div><div><b>${q.avg?q.avg.toFixed(1):'-'}</b><span>Avg hours</span></div><div><b class="${q.abs?'bad':''}">${q.abs}</b><span>Absent</span></div>
  <div><b class="${q.late?'bad':''}">${k=='o'?'-':q.late}</b><span>Late</span></div><div><b>${k=='o'?'-':hs(q.ot)}</b><span>Overtime</span></div><div><b class="${q.miss?'bad':''}">${q.miss}</b><span>Missing</span></div></div></div>`}).join('');
 ROWS=agg(l);sortRows();
 chart();heat();attention();list();nowCard();alerts();fsum();if(DR!=null)drawer()}

function chart(){
 const base=ses.filter(s=>(F.dept=='all'||s.e.dept==F.dept)&&hit(s));
 const ds=dates,c={};ds.forEach(d=>c[d]={m:0,e:0,o:0,a:0});base.forEach(s=>{if(s.abs)c[s.d].a++;else c[s.d][s.k]++});
 const tot=d=>c[d].m+c[d].e+c[d].o+c[d].a,mx=Math.max(1,...ds.map(tot));
 const W=720,H=200,L=34,R=6,T=12,Bm=36,pw=W-L-R,ph=H-T-Bm,bw=pw/Math.max(ds.length,1);let g='';
 [0,.5,1].forEach(f=>{const y=T+ph-f*ph;g+=`<line x1="${L}" x2="${W-R}" y1="${y}" y2="${y}" stroke="var(--line)"/><text x="${L-6}" y="${y+3}" text-anchor="end">${Math.round(mx*f)}</text>`});
 ds.forEach((d,i)=>{const x=L+i*bw;let y=T+ph;
  for(const k of['m','e','o','a']){const h=c[d][k]/mx*ph;if(h<=0)continue;y-=h;g+=`<rect data-b style="--i:${i}" x="${x+1.5}" y="${y}" width="${Math.max(1,bw-3)}" height="${h}" fill="var(--${k=='a'?'bad':k})"${k=='a'?' opacity=".55"':''}/>`}
  const sel=F.from!=null&&d>=F.from&&d<=F.to;
  g+=`<rect data-d="${d}" x="${x}" y="${T}" width="${bw}" height="${ph+Bm-4}" fill="transparent"${sel?' stroke="var(--ink)" stroke-width="1.5" rx="3"':''} style="cursor:pointer"><title>${dl(d)}: ${c[d].m+c[d].e+c[d].o} present, ${c[d].a} absent</title></rect>`;
  const step=ds.length>20?2:1;
  if(i%step==0)g+=`<text x="${x+bw/2}" y="${H-18}" text-anchor="middle">${dd(d).getUTCDate()}</text><text x="${x+bw/2}" y="${H-6}" text-anchor="middle" style="font-size:8.5px">${dd(d).toLocaleDateString('en-GB',{weekday:'narrow',timeZone:'UTC'})}</text>`});
 $('#chart').innerHTML=`<svg viewBox="0 0 ${W} ${H}" role="img" aria-label="Daily headcount">${g}</svg>`}

function heat(){
 const base=ses.filter(s=>(F.dept=='all'||s.e.dept==F.dept)&&hit(s)),c={};dates.forEach(d=>c[d]={p:0,a:0});base.forEach(s=>{if(s.abs)c[s.d].a++;else c[s.d].p++});
 const wd=d=>(dd(d).getUTCDay()+6)%7;let cols=[],col=new Array(7).fill(null),i=0;
 dates.forEach((d,ix)=>{const w=wd(d);if(ix&&w==0){cols.push(col);col=new Array(7).fill(null)}col[w]=d});cols.push(col);
 const nm=['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
 let h='<div class="hd">'+nm.map(n=>`<span>${n}</span>`).join('')+'</div>';
 cols.forEach(cl=>{const f=cl.find(x=>x!=null);h+=`<div class="hw"><small>${f==null?'':dd(f).toLocaleDateString('en-GB',{day:'numeric',month:'short',timeZone:'UTC'})}</small>`+cl.map(d=>{if(d==null)return '<span class="hc e"></span>';const t=c[d].p+c[d].a;if(!t)return `<span class="hc x" title="${dl(d)}: no data"></span>`;const r=c[d].p/t;return `<span class="hc" style="--r:${r.toFixed(3)};--i:${i++}" title="${dl(d)}: ${Math.round(r*100)}% present (${c[d].p} present, ${c[d].a} absent)">${dd(d).getUTCDate()}</span>`}).join('')+'</div>'});
 $('#heat').innerHTML=h}

function attention(){
 const blk=(t,rows,f)=>`<div class="att${rows.length?' al':''}"><h3>${t}</h3>`+(rows.length?rows.map(r=>`<button data-f="${esc(r.e.id)}"><span>${esc(r.e.name)}</span><b class="bad">${f(r)}</b></button>`).join(''):'<div class="none">Nobody in this range.</div>')+'</div>';
 const a=ROWS.filter(r=>r.q.abs>0).sort((x,y)=>y.q.abs-x.q.abs||x.e.name.localeCompare(y.e.name)).slice(0,5);
 const b=ROWS.filter(r=>r.q.late>0).sort((x,y)=>y.q.late-x.q.late||x.e.name.localeCompare(y.e.name)).slice(0,5);
 $('#att').innerHTML=blk('Most absent',a,r=>r.q.abs+' days')+blk('Most late',b,r=>r.q.late+' times')}

const SORTS=[['late','Most late'],['abs','Absent'],['rate','Lowest attendance'],['ot','Overtime'],['miss','Missing'],['hrs','Avg hours'],['name','Name']];
function sortRows(){
 const key={abs:r=>-r.q.abs,late:r=>-r.q.late,ot:r=>-r.q.ot,miss:r=>-r.q.miss,hrs:r=>-r.q.avg,rate:r=>rateOf(r.q)==null?101:rateOf(r.q)};
 ROWS.sort(F.sort=='name'?(a,b)=>a.e.name.localeCompare(b.e.name):(a,b)=>key[F.sort](a)-key[F.sort](b)||a.e.name.localeCompare(b.e.name))}

function strip(r){
 const ds=dates.filter(d=>F.from==null||(d>=F.from&&d<=F.to)),m=new Map(r.l.map(s=>[s.d,s]));
 return '<div class="strip" aria-label="Day by day">'+ds.map(d=>{const s=m.get(d);const c=!s?'':s.abs?'a':(s.late||s.noin||(!s.b&&!s.open))?'l':'p';
  return `<i class="${c||'x'}" title="${dl(d)}: ${s?statusText(s):'No record'}"></i>`}).join('')+'</div>'}

const bulkRows=()=>ROWS.filter(r=>F.shift=='all'||r.k==F.shift);
const asgSel=r=>{const v=ASG[String(r.e.id)]||'';
 return CANASG?`<label class="sub2" style="display:flex;gap:6px;align-items:center">Shift <select data-asg="${esc(r.e.id)}"><option value=""${v==''?' selected':''}>Auto-detect</option><option value="m"${v=='m'?' selected':''}>Morning (fixed)</option><option value="e"${v=='e'?' selected':''}>Evening (fixed)</option></select></label>`:(v?`<span class="sub2">Shift fixed: ${NM[v]}</span>`:'')};
const hue=s=>{let h=0;for(const c of String(s))h=(h*31+c.charCodeAt(0))%360;return h};
const stc=q=>q.abs>q.n*.25&&q.abs>0?['ab','Mostly absent']:q.late>0||q.abs>0?['lt',q.late?'Late '+q.late+'x':'Absent '+q.abs+'x']:['ok','On time'];
let lsig='';
function list(){
 {const sg=ROWS.slice(0,F.n).map(r=>r.e.id).join(',');$('#list').classList.toggle('ani',!RM&&sg!==lsig);lsig=sg}
 const bn=bulkRows().length;$('#bulk').hidden=!CANASG||!ROWS.length;$('#bulkt').textContent=F.shift=='all'?`Set the shift for all ${bn} employee${bn==1?'':'s'} shown:`:`Set the shift for the ${bn} employee${bn==1?'':'s'} whose main shift is ${NM[F.shift]}:`;
 $('#sorts').innerHTML='<span class="sub2">Sort by</span>'+SORTS.map(([k,t])=>`<button data-o="${k}" aria-pressed="${F.sort==k}">${t}</button>`).join('');
 let h=ROWS.slice(0,F.n).map((r,ix)=>{const q=r.q,op=false,rt=rateOf(q);let d='';
  if(op)d='<div class="dl"><div class="act"><button class="btn pri" data-x="pdf" data-id="'+esc(r.e.id)+'">Download PDF report</button><button class="btn" data-x="xls" data-id="'+esc(r.e.id)+'">Download Excel</button>'+asgSel(r)+'</div>'+strip(r)+
   r.l.slice().sort((a,b)=>b.a-a.a).map(s=>s.abs?`<div class="ln"><span>${dl(s.d)} <span class="pill ${s.k}">${NM[s.k]}</span></span><span class="bad">Absent | no punch</span></div>`:
    `<div class="ln"><span>${dl(s.d)} <span class="pill ${s.k}">${NM[s.k]}</span></span><span>${inS(s)} to ${outS(s)}${s.b&&!s.noin?' | '+hs(s.h*60):''}</span><span>${s.late?`<span class="pill bad">Late ${mn(s.late)}</span> `:''}${s.early?`<span class="pill bad">Left ${mn(s.early)} early</span> `:''}${s.ot?`<span class="pill good">OT ${mn(s.ot)}</span>`:''}</span></div>`).join('')+'</div>';
  const mc=(v,l,c)=>`<div class="mc"><b class="${c||''}">${v}</b><small>${l}</small></div>`;
  return `<div class="emp" style="--i:${Math.min(ix,12)}"><button data-e="${esc(r.e.id)}" aria-expanded="${op}"><div class="er"><div class="nmw"><span class="av" style="--h:${hue(r.e.name)}">${esc((String(r.e.name).trim()[0]||'?').toUpperCase())}</span><div class="nm">${esc(r.e.name)}<span class="stb ${stc(q)[0]}">${stc(q)[1]}</span><span class="pill ${r.k}">${NM[r.k]}${ASG[String(r.e.id)]?' (fixed)':''}</span><small>#${esc(r.e.id)} | ${esc(r.e.dept)} | ${rtxt(q)} attendance</small><div class="rate"><i style="width:${rt==null?0:rt}%;background:${rt==null||rt>=95?'var(--good)':rt>=85?'var(--warn)':'var(--bad)'}"></i></div></div></div>
  ${mc(q.n,'present')}${mc(q.abs,'absent',q.abs?'bad':'')}${mc(q.late,'late',q.late?'bad':'')}${mc(q.miss,'missing',q.miss?'bad':'')}${mc(q.avg?q.avg.toFixed(1):'-','avg hours')}${mc(hs(q.ot),'overtime')}</div></button>${d}</div>`}).join('');
 if(ROWS.length>F.n)h+=`<button class="btn" id="more" style="width:100%;justify-content:center;margin-top:6px">Show ${Math.min(40,ROWS.length-F.n)} more (${ROWS.length-F.n} left)</button>`;
 $('#list').innerHTML=h||'<p class="sub2">No one matches these filters. Clear the search or pick All dates.</p>'}

/* ---------- events ---------- */
document.addEventListener('click',e=>{
 const t=e.target.closest('[data-s],[data-o],[data-e],[data-d],[data-r],[data-f],[data-x],[data-bulk],#more');if(!t)return;
 if(t.dataset.bulk!==undefined){bulk(t.dataset.bulk);return}
 if(t.dataset.x){exportEmp(t.dataset.x,t.dataset.id);return}
 if(t.dataset.f){openEmp(t.dataset.f);return}
 if(t.dataset.s)F.shift=F.shift==t.dataset.s&&t.classList.contains('sc')?'all':t.dataset.s;
 else if(t.dataset.o)F.sort=t.dataset.o;
 else if(t.dataset.e){openEmp(t.dataset.e);return}
 else if(t.dataset.d){const d=+t.dataset.d;if(F.from==d&&F.to==d)F.from=F.to=null;else F.from=F.to=d}
 else if(t.dataset.r){const n=+t.dataset.r;if(n&&dates.length){F.to=dates[dates.length-1];F.from=F.to-n+1}else F.from=F.to=null}
 else if(t.id=='more')F.n+=40;
 render()});
document.addEventListener('keydown',e=>{if((e.key=='Enter'||e.key==' ')&&(e.target.classList.contains('sc')||e.target.matches('label[tabindex]'))){e.preventDefault();e.target.click()}});
async function assign(ids,val,clear,silent){
 const prev=clear?Object.keys(ASG).map(k=>[k,ASG[k]]):ids.map(i=>[String(i),ASG[String(i)]||'']);
 const set={};ids.forEach(i=>set[i]=val);
 if(USER.role==='admin'){
  try{const r=await(await fetch('api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({a:'assign',csrf:CSRF,set,clear:!!clear})})).json();
   if(!r.ok){toast(r.msg,5000);render();return}}
  catch(x){toast('Server error. Please try again.',5000);render();return}}
 if(clear)ASG={};else ids.forEach(i=>{if(val)ASG[String(i)]=val;else delete ASG[String(i)]});
 if(USER.role==='customer'){try{localStorage.setItem(ASGK,JSON.stringify(ASG))}catch(x){}}
 build();render();if(silent)return;
 const undo={label:'Undo',fn:async()=>{const g={};prev.forEach(([i,v])=>(g[v]=g[v]||[]).push(i));if(clear&&!prev.length){toast('Nothing to undo');return}for(const v of Object.keys(g))await assign(g[v],v,false,true);toast('Undone')}};
 toast(clear?'All fixed shifts cleared':ids.length>1?`Shift updated for ${ids.length} employees`:'Shift updated',0,undo)}
function bulk(val){const ids=bulkRows().map(r=>String(r.e.id)),nm=val?NM[val]:'Auto-detect';
 if(ids.length&&confirm(`Set ${ids.length} employee${ids.length==1?'':'s'} to ${nm}?`))assign(ids,val)}
document.addEventListener('change',e=>{const t=e.target.closest('[data-asg]');if(t)assign([t.dataset.asg],t.value)});
if($('#clrasg'))$('#clrasg').onclick=()=>{if(confirm('Remove all fixed shifts and go back to auto-detect for everyone?'))assign([],'',true)};
$('#dept').onchange=e=>{F.dept=e.target.value;F.n=40;render()};
const rd=()=>{let a=$('#dfrom').value,b=$('#dto').value;a=a?dn(a):null;b=b?dn(b):null;
 if(a==null&&b==null)F.from=F.to=null;else{if(a==null)a=dates[0];if(b==null)b=dates[dates.length-1];if(a>b)[a,b]=[b,a];F.from=a;F.to=b}
 F.n=40;render()};
$('#dfrom').onchange=rd;$('#dto').onchange=rd;
$('#q').oninput=e=>{F.q=e.target.value.trim().toLowerCase();F.n=40;render()};

/* shift settings (this page only; remembered for customers) */
function setIn(){const v=(id,m)=>$(id).value=fm(m);v('#ms',P.m.s);v('#me',P.m.e);v('#es',P.e.s);v('#ee',P.e.e);$('#gr').value=P.grace;$('#ot').value=P.ot;$('#tl').value=P.tol;$('#wo').value=P.off}
function readIn(){const g=(id,d)=>hm($(id).value)??d,en=(s,e)=>e<=s?e+1440:e;
 P.m.s=g('#ms',540);P.m.e=en(P.m.s,g('#me',1080));P.e.s=g('#es',900);P.e.e=en(P.e.s,g('#ee',120));
 P.grace=+$('#gr').value||0;P.ot=+$('#ot').value||0;P.tol=+$('#tl').value||120;P.off=+$('#wo').value;
 if(USER.role==='customer'){try{localStorage.setItem(LSK,JSON.stringify({m:{s:P.m.s,e:P.m.e},e:{s:P.e.s,e:P.e.e},grace:P.grace,ot:P.ot,tol:P.tol,off:P.off}))}catch(x){}}
 build();ruler();render()}
['#ms','#me','#es','#ee','#gr','#ot','#tl','#wo'].forEach(i=>$(i).onchange=readIn);
$('#rst').onclick=()=>{applyCfg(CFG);if(USER.role==='customer'){try{localStorage.removeItem(LSK)}catch(x){}}setIn();build();ruler();render()};

document.documentElement.dataset.theme='dark';

/* ---------- opening a file ---------- */
function resetF(){F.shift='all';F.dept='all';F.from=F.to=null;F.q='';F.open=null;F.n=40;$('#q').value=''}
/* ---------- AI analysis animation ---------- */
let botBusy=false,skipBot=false;
const sleep=ms=>new Promise(r=>setTimeout(r,(skipBot||RM)?Math.min(ms,50):ms));
const nf=n=>Number(n).toLocaleString('en-US');
function botOpen(){botBusy=true;skipBot=false;$('#botS').innerHTML='';$('#botB').style.width='0%';
 $('#botT').innerHTML='Analysing your file<span class="dots"><span>.</span><span>.</span><span>.</span></span>';
 $('#bot').classList.remove('happy','bad');$('#botX').hidden=true;$('#botSkip').hidden=false;$('#bot').hidden=false;$('#botSkip').focus()}
function botStep(t){const li=document.createElement('li');li.innerHTML='<i class="sp"></i><span></span>';li.lastChild.textContent=t;$('#botS').appendChild(li);return li}
const botDone=(li,t)=>{li.classList.add('ok');if(t)li.lastChild.textContent=t};
const botPct=p=>{$('#botB').style.width=p+'%'};
function botClose(){$('#bot').hidden=true;botBusy=false}
$('#botSkip').onclick=()=>{skipBot=true};
$('#botX').onclick=botClose;
document.addEventListener('keydown',e=>{if(e.key=='Escape'&&!$('#bot').hidden&&!$('#botX').hidden)botClose()});

async function loadFile(f){
 if(botBusy)return;
 const keep={RAW,SRC};botOpen();
 try{
  const buf=await f.arrayBuffer();
  let s=botStep(`Reading ${f.name}`);botPct(8);await sleep(600);
  if(typeof XLSX=='undefined')throw new Error('lib');
  const rows=parseAttendance(buf);
  if(!rows.length)throw new Error('empty');
  RAW=rows;SRC=f.name;resetF();build();
  if(!ses.length)throw new Error('empty');
  const emps=new Set(ses.map(x=>x.e.id)).size;
  botDone(s,`Read ${nf(rows.length)} punch records for ${nf(emps)} employees`);botPct(26);
  s=botStep('Matching in and out punches, even across midnight');await sleep(650);
  const pr=ses.filter(x=>!x.abs);botDone(s,`Matched ${nf(pr.length)} shifts`);botPct(48);
  s=botStep('Detecting Morning and Evening shifts');await sleep(650);
  const c={m:0,e:0,o:0};pr.forEach(x=>c[x.k]++);
  botDone(s,`${nf(c.m)} morning, ${nf(c.e)} evening, ${nf(c.o)} other`);botPct(70);
  s=botStep('Checking late arrivals and absences');await sleep(650);
  const st=stats(ses);botDone(s,`${nf(st.late)} late, ${nf(st.abs)} absent days, ${nf(st.miss)} missing punches`);botPct(90);
  s=botStep('Building your dashboard');await sleep(550);botDone(s,'Your dashboard is ready');botPct(100);
  $('#botT').textContent='All done';$('#bot').classList.add('happy');await sleep(600);
  botClose();LOCAL=true;$('#emptyerr').textContent='';render();window.scrollTo({top:0});reveal()
 }catch(x){
  RAW=keep.RAW;SRC=keep.SRC;build();
  const m=x.message=='lib'?'The spreadsheet reader did not load. Refresh the page and try again.':x.message=='empty'?'No attendance records were found in that file.':'Could not read that file. It needs the columns Employee ID, First Name, Date, First Punch and Last Punch.';
  $('#botT').textContent="I couldn't read that file";$('#bot').classList.add('bad');
  const last=$('#botS').lastElementChild;if(last)last.classList.remove('ok');
  botStep(m).classList.add('bad');$('#botSkip').hidden=true;$('#botX').hidden=false;$('#botX').focus();$('#emptyerr').textContent=m;render()}}

/* ---------- animated reveal ---------- */
let rvT;
function countUps(){
 if(RM)return;
 document.querySelectorAll('#kpis .kpi b,#cards .st b').forEach(el=>{
  const m=el.textContent.match(/^(-?\d[\d,]*\.?\d*)(.*)$/);if(!m)return;
  const end=parseFloat(m[1].replace(/,/g,'')),dec=(m[1].split('.')[1]||'').length,suf=m[2],plain=!m[1].includes(',');
  if(!isFinite(end)||end===0)return;
  el.textContent=(0).toFixed(dec)+suf;const t0=performance.now()+350,dur=1100;
  const step=t=>{const k=Math.min(1,Math.max(0,(t-t0)/dur)),e=1-Math.pow(1-k,3);el.textContent=(end*e).toFixed(dec)+suf;if(k<1)requestAnimationFrame(step)};
  requestAnimationFrame(step)})}
function reveal(){const a=$('#app');if(RM||a.hidden)return;a.classList.remove('rv');void a.offsetWidth;a.classList.add('rv');countUps();clearTimeout(rvT);rvT=setTimeout(()=>a.classList.remove('rv'),3600)}

$('#f').onchange=e=>{const f=e.target.files[0];e.target.value='';if(f)loadFile(f)};
{const z=$('#empty');['dragenter','dragover'].forEach(n=>document.addEventListener(n,e=>{e.preventDefault();z.classList.add('on')}));
 ['dragleave','drop'].forEach(n=>document.addEventListener(n,e=>{e.preventDefault();z.classList.remove('on')}));
 document.addEventListener('drop',e=>{const f=e.dataTransfer&&e.dataTransfer.files[0];if(f)loadFile(f)})}
$('#upback').onclick=()=>{if(USER.role==='customer'){LOCAL=false;RAW=[];SRC='';resetF();build();render();$('#sub').textContent='Attendance dashboard'}else location.reload()};
if($('#upsave'))$('#upsave').onclick=async()=>{const b=$('#upsave');b.disabled=true;
 try{const r=await(await fetch('api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({a:'data',csrf:CSRF,rows:RAW,file:SRC,merge:$('#upm').checked})})).json();
  toast(r.msg,4000);if(r.ok)setTimeout(()=>location.reload(),1200);else b.disabled=false}
 catch(x){toast('Server error. Please try again.');b.disabled=false}};

/* ---------- reports: PDF and Excel ---------- */
const slug=s=>String(s).replace(/[^\w.-]+/g,'_').replace(/^_+|_+$/g,'')||'file';
const hex=h=>[1,3,5].map(i=>parseInt(h.slice(i,i+2),16));
const INK=[20,32,51],MUT=[91,104,120],BAD=[179,38,30],GOOD=[18,128,92],WARN=[178,106,0];
const TONE={bad:BAD,good:GOOD,mut:MUT};
let LOGOP=null;
function getLogo(){if(LOGOP)return LOGOP;
 LOGOP=new Promise(res=>{if(!BR.logo)return res(null);const i=new Image();
  i.onload=()=>{try{const c=document.createElement('canvas');c.width=i.naturalWidth;c.height=i.naturalHeight;c.getContext('2d').drawImage(i,0,0);res({u:c.toDataURL('image/png'),w:i.naturalWidth,h:i.naturalHeight})}catch(e){res(null)}};
  i.onerror=()=>res(null);i.src=BR.logo});return LOGOP}
function range(){return{a:F.from!=null?F.from:dates[0],b:F.from!=null?F.to:dates[dates.length-1]}}
const periodText=()=>{const r=range();return dlong(r.a)+' to '+dlong(r.b)};
const periodKey=()=>{const r=range();return iso(r.a)+'_to_'+iso(r.b)};
function filterText(){const p=[];p.push('Period: '+periodText());if(F.dept!='all')p.push('Department: '+F.dept);if(F.shift!='all')p.push('Shift: '+NM[F.shift]);if(F.q)p.push('Search: '+F.q);return p.join('  |  ')}
function rulesText(){return `Morning ${t12(P.m.s)} - ${t12(P.m.e)}  |  Evening ${t12(P.e.s)} - ${t12(P.e.e)}  |  Late/early grace ${P.grace} min  |  Overtime after ${P.ot} min. Hours use each day's first and last punch.`}
const need=k=>{if(k=='pdf'&&!(window.jspdf&&window.jspdf.jsPDF)){toast('The PDF library did not load. Refresh the page and try again.',5000);return false}
 if(k=='xls'&&typeof XLSX=='undefined'){toast('The Excel library did not load. Refresh the page and try again.',5000);return false}return true};
const dayRows=l=>l.slice().sort((x,y)=>x.d-y.d||x.a-y.a);

async function newDoc(landscape,title,sub){
 const doc=new window.jspdf.jsPDF({orientation:landscape?'landscape':'portrait',unit:'pt',format:'a4'});
 const W=doc.internal.pageSize.getWidth(),acc=hex(BR.accent),on=hex(BR.on),logo=await getLogo();
 doc.setFillColor(...acc);doc.rect(0,0,W,76,'F');let x=36;
 if(logo){doc.setFillColor(255,255,255);doc.roundedRect(36,13,50,50,10,10,'F');const k=Math.min(40/logo.w,40/logo.h),w=logo.w*k,h=logo.h*k;doc.addImage(logo.u,'PNG',36+(50-w)/2,13+(50-h)/2,w,h);x=98}
 doc.setTextColor(...on);doc.setFont('helvetica','bold');doc.setFontSize(17);doc.text(BR.name,x,40);
 doc.setFont('helvetica','normal');doc.setFontSize(9);doc.text('Attendance report',x,55);
 doc.setFont('helvetica','bold');doc.setFontSize(13);doc.text(title,W-36,36,{align:'right'});
 doc.setFont('helvetica','normal');doc.setFontSize(9);doc.text(sub,W-36,54,{align:'right'});
 return{doc,W,acc,on}}
function footers(doc){const n=doc.getNumberOfPages(),W=doc.internal.pageSize.getWidth(),H=doc.internal.pageSize.getHeight();
 for(let i=1;i<=n;i++){doc.setPage(i);doc.setFontSize(8);doc.setTextColor(...MUT);doc.setFont('helvetica','normal');
  doc.text(`${BR.name} | generated ${new Date().toLocaleString('en-GB',{day:'numeric',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'})} by ${USER.name}`,36,H-22);
  doc.text(`Page ${i} of ${n}`,W-36,H-22,{align:'right'})}}
function kpiBoxes(doc,W,y,items){const gap=8,bw=(W-72-gap*(items.length-1))/items.length;
 items.forEach(([lab,val,c],i)=>{const x=36+i*(bw+gap);doc.setDrawColor(217,223,231);doc.setFillColor(246,248,251);doc.roundedRect(x,y,bw,50,7,7,'FD');
  doc.setFont('helvetica','bold');doc.setFontSize(16);doc.setTextColor(...(c||INK));doc.text(String(val),x+bw/2,y+25,{align:'center'});
  doc.setFont('helvetica','normal');doc.setFontSize(8);doc.setTextColor(...MUT);doc.text(lab,x+bw/2,y+40,{align:'center'})});return y+50}
function after(doc,y,text){const H=doc.internal.pageSize.getHeight(),W=doc.internal.pageSize.getWidth();
 if(y>H-80){doc.addPage();y=50}doc.setFont('helvetica','normal');doc.setFontSize(8);doc.setTextColor(...MUT);doc.text(doc.splitTextToSize(text,W-72),36,y)}
const kp6=q=>[['Attendance',rtxt(q),rateOf(q)==null||rateOf(q)>=95?GOOD:rateOf(q)>=85?WARN:BAD],['Present days',q.n],['Absent days',q.abs,q.abs?BAD:null],['Late',q.late,q.late?BAD:null],['Overtime',hs(q.ot),GOOD],['Avg hours',q.avg?q.avg.toFixed(1):'-']];

async function exportEmp(kind,id){
 const r=ROWS.find(x=>String(x.e.id)==String(id));if(!r||!need(kind))return;
 const l=dayRows(r.l),q=r.q,fn=`${slug(BR.name)}_${slug(r.e.name)}_${periodKey()}`;
 try{
  if(kind=='pdf'){toast('Preparing PDF...',1500);
   const{doc,W,acc,on}=await newDoc(false,'Employee attendance report',periodText());let y=104;
   doc.setTextColor(...INK);doc.setFont('helvetica','bold');doc.setFontSize(17);doc.text(r.e.name,36,y);
   doc.setFont('helvetica','normal');doc.setFontSize(10);doc.setTextColor(...MUT);doc.text(`ID ${r.e.id}   |   ${r.e.dept}   |   Main shift: ${NM[r.k]}`,36,y+18);
   y=kpiBoxes(doc,W,y+34,kp6(q))+16;
   doc.autoTable({startY:y,margin:{left:36,right:36,top:44,bottom:44},head:[['Date','Shift','In','Out','Hours','Status']],
    body:l.map(s=>[dlong(s.d),s.k=='o'?'Other':NM[s.k],s.abs||s.noin?'-':t12(s.a),s.abs||!s.b?'-':t12(s.b),s.b&&!s.abs&&!s.noin?s.h.toFixed(1):'-',statusText(s)]),
    styles:{fontSize:9,cellPadding:4,textColor:INK},headStyles:{fillColor:acc,textColor:on},alternateRowStyles:{fillColor:[246,248,251]},columnStyles:{4:{halign:'right'}},
    didParseCell:h=>{if(h.section=='body'&&h.column.index==5){h.cell.styles.textColor=TONE[tone(l[h.row.index])];h.cell.styles.fontStyle='bold'}}});
   after(doc,doc.lastAutoTable.finalY+16,rulesText());footers(doc);doc.save(fn+'.pdf')}
  else{
   const wb=XLSX.utils.book_new();
   const sum=[[BR.name+' - Employee attendance report'],[periodText()],[],['Employee',r.e.name],['Employee ID',r.e.id],['Department',r.e.dept],['Main shift',NM[r.k]],[],['Attendance %',rateOf(q)==null?'':+rateOf(q).toFixed(1)],['Present days',q.n],['Absent days',q.abs],['Late days',q.late],['Missing out punch',q.miss],['Average hours',+q.avg.toFixed(2)],['Overtime hours',+(q.ot/60).toFixed(2)],[],[rulesText()]];
   const w1=XLSX.utils.aoa_to_sheet(sum);w1['!cols']=[{wch:22},{wch:34}];XLSX.utils.book_append_sheet(wb,w1,'Summary');
   const w2=XLSX.utils.aoa_to_sheet([['Date','Day','Shift','In','Out','Hours','Status'],...l.map(s=>[iso(s.d),dd(s.d).toLocaleDateString('en-GB',{weekday:'short',timeZone:'UTC'}),s.k=='o'?'Other':NM[s.k],s.abs||s.noin?'':t12(s.a),s.abs||!s.b?'':t12(s.b),s.b&&!s.abs&&!s.noin?+s.h.toFixed(2):'',statusText(s)])]);
   w2['!cols']=[{wch:12},{wch:6},{wch:10},{wch:10},{wch:10},{wch:8},{wch:30}];XLSX.utils.book_append_sheet(wb,w2,'Daily');XLSX.writeFile(wb,fn+'.xlsx')}
 }catch(x){console.error(x);toast('Could not create the report: '+(x.message||x),6000)}}

async function exportAll(kind){
 if(!ROWS.length||!need(kind))return;const q=stats(fl()),fn=`${slug(BR.name)}_Attendance_Summary_${periodKey()}`;
 try{
  if(kind=='pdf'){toast('Preparing PDF...',1500);
   const{doc,W,acc,on}=await newDoc(true,'Attendance summary',periodText());let y=100;
   doc.setFont('helvetica','normal');doc.setFontSize(9.5);doc.setTextColor(...MUT);doc.text(filterText(),36,y);
   y=kpiBoxes(doc,W,y+12,[...kp6(q).slice(0,5),['Employees',q.people]])+14;
   doc.autoTable({startY:y,margin:{left:36,right:36,top:44,bottom:44},head:[['#','ID','Employee','Department','Shift','Present','Absent','Late','Missing','Avg h','OT h','Attend.']],
    body:ROWS.map((r,i)=>[i+1,r.e.id,r.e.name,r.e.dept,NM[r.k],r.q.n,r.q.abs,r.q.late,r.q.miss,r.q.avg?r.q.avg.toFixed(1):'-',(r.q.ot/60).toFixed(1),rtxt(r.q)]),
    styles:{fontSize:8.5,cellPadding:3,textColor:INK},headStyles:{fillColor:acc,textColor:on},alternateRowStyles:{fillColor:[246,248,251]},
    columnStyles:{0:{halign:'right'},5:{halign:'center'},6:{halign:'center'},7:{halign:'center'},8:{halign:'center'},9:{halign:'center'},10:{halign:'center'},11:{halign:'center'}},
    didParseCell:h=>{if(h.section!='body')return;const r=ROWS[h.row.index],c=h.column.index;
     if(c==6&&r.q.abs)h.cell.styles.textColor=BAD;if(c==7&&r.q.late)h.cell.styles.textColor=BAD;if(c==8&&r.q.miss)h.cell.styles.textColor=BAD;
     if(c==11){const v=rateOf(r.q);h.cell.styles.textColor=v==null||v>=95?GOOD:v>=85?WARN:BAD;h.cell.styles.fontStyle='bold'}}});
   after(doc,doc.lastAutoTable.finalY+16,rulesText());footers(doc);doc.save(fn+'.pdf')}
  else{
   const wb=XLSX.utils.book_new();
   const head=['ID','Employee','Department','Main shift','Present days','Absent days','Late days','Missing out punch','Avg hours','Overtime hours','Attendance %'];
   const w1=XLSX.utils.aoa_to_sheet([[BR.name+' - Attendance summary'],[filterText()],[rulesText()],[],head,...ROWS.map(r=>[r.e.id,r.e.name,r.e.dept,NM[r.k],r.q.n,r.q.abs,r.q.late,r.q.miss,+r.q.avg.toFixed(2),+(r.q.ot/60).toFixed(2),rateOf(r.q)==null?'':+rateOf(r.q).toFixed(1)])]);
   w1['!cols']=[{wch:10},{wch:28},{wch:16},{wch:10},{wch:12},{wch:12},{wch:10},{wch:16},{wch:10},{wch:14},{wch:13}];XLSX.utils.book_append_sheet(wb,w1,'Employees');
   const rows=[];ROWS.forEach(r=>dayRows(r.l).forEach(s=>rows.push([r.e.id,r.e.name,r.e.dept,iso(s.d),dd(s.d).toLocaleDateString('en-GB',{weekday:'short',timeZone:'UTC'}),s.k=='o'?'Other':NM[s.k],s.abs||s.noin?'':t12(s.a),s.abs||!s.b?'':t12(s.b),s.b&&!s.abs&&!s.noin?+s.h.toFixed(2):'',statusText(s)])));
   rows.sort((a,b)=>a[3]<b[3]?-1:a[3]>b[3]?1:String(a[1]).localeCompare(String(b[1])));
   const w2=XLSX.utils.aoa_to_sheet([['ID','Employee','Department','Date','Day','Shift','In','Out','Hours','Status'],...rows]);
   w2['!cols']=[{wch:10},{wch:28},{wch:16},{wch:12},{wch:6},{wch:10},{wch:10},{wch:10},{wch:8},{wch:30}];XLSX.utils.book_append_sheet(wb,w2,'Daily');XLSX.writeFile(wb,fn+'.xlsx')}
 }catch(x){console.error(x);toast('Could not create the report: '+(x.message||x),6000)}}
$('#bpdf').onclick=()=>exportAll('pdf');$('#bxls').onclick=()=>exportAll('xls');$('#bprint').onclick=()=>window.print();

/* ---------- start ---------- */

/* ---------- subscription countdown (customers and viewers) ---------- */
{
 const pill=$('#tpill');
 let skew=((SUB.now||0)*1000)-Date.now(),sub=SUB,ended=false,warned={};
 const fmt=sec=>{sec=Math.max(0,Math.floor(sec));const d=Math.floor(sec/86400),h=Math.floor(sec%86400/3600),m=Math.floor(sec%3600/60),x=sec%60,p=n=>String(n).padStart(2,'0');return(d?d+'d ':'')+p(h)+':'+p(m)+':'+p(x)};
 function end(){if(ended)return;ended=true;$('#endov').hidden=false;setTimeout(()=>{location.href='expired.php'},6000)}
 function paint(){
  if(!pill)return;
  if(sub.s!=='active'){pill.hidden=true;return}
  const left=sub.exp-(Date.now()+skew)/1000;
  if(left<=0){pill.hidden=false;$('#tcd').textContent='00:00:00';end();return}
  pill.hidden=false;$('#tcd').textContent=fmt(left);
  pill.classList.toggle('crit',left<=3600);pill.classList.toggle('warn',left>3600&&left<=86400);
  pill.title='Your subscription ends on '+new Date(sub.exp*1000).toLocaleString([],{weekday:'short',day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'});
  const tot=sub.dur||Math.max(left,1),C=87.96;$('#tring').setAttribute('stroke-dashoffset',C*(1-Math.min(1,left/tot)));
  if(left<=600&&!warned.a){warned.a=1;toast('Your subscription ends in 10 minutes',6000)}
  if(left<=60&&!warned.b){warned.b=1;toast('Your subscription ends in 1 minute',6000)}}
 async function sync(){
  if(ended)return;
  try{const r=await(await fetch('api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({a:'sub',csrf:CSRF})})).json();
   if(r.expired){sub={s:'expired'};end();return}
   if(r.ok&&r.sub){sub=r.sub;skew=r.now*1000-Date.now();warned={};paint()}}catch(e){}}
 if(pill&&SUB.s==='active'){paint();setInterval(paint,1000);setInterval(sync,30000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)sync()})}
}

setIn();build();ruler();render();if(ses.length)reveal();

/* one-time welcome popup (marked as seen on the server as soon as it is shown) */
if(USER.welcome){const o=$('#welov');o.hidden=false;
 fetch('api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({a:'seen',csrf:CSRF})}).catch(()=>{});
 const x=()=>{o.hidden=true};$('#welok').onclick=x;o.addEventListener('click',e=>{if(e.target===o)x()});addEventListener('keydown',e=>{if(e.key==='Escape')x()});$('#welok').focus()}

/* top bar: "Hide" button in the bar, small "Menu" tab brings it back (choice is remembered in this browser) */
(()=>{const t=document.querySelector('.top'),sp=$('#topsp'),g=$('#topgrab'),hb=$('#tophide');if(!t||!sp||!g||!hb)return;
 let hid=false;
 const set=(h,save)=>{hid=h;t.classList.toggle('hid',h);document.body.classList.toggle('hidtop',h);t.inert=h;sp.style.height=h?'0px':t.offsetHeight+'px';if(save){try{localStorage.setItem('topHidden',h?'1':'0')}catch(e){}}};
 hb.onclick=()=>set(true,true);g.onclick=()=>{set(false,true);hb.focus()};
 addEventListener('resize',()=>{if(!hid)sp.style.height=t.offsetHeight+'px'});
 let s=null;try{s=localStorage.getItem('topHidden')}catch(e){}
 sp.style.height=t.offsetHeight+'px';if(s==='1')set(true,false)})();

/* 3D hover: tiles and shift cards tilt toward the cursor and catch a light glare (not on touch, not while the reveal animation plays) */
(()=>{if(RM||!matchMedia('(hover:hover)').matches)return;
 const sel='.kpi:not(.bad),.sc';
 document.addEventListener('pointermove',e=>{const el=e.target.closest&&e.target.closest(sel);if(!el||$('#app').classList.contains('rv'))return;
  const r=el.getBoundingClientRect(),x=(e.clientX-r.left)/r.width,y=(e.clientY-r.top)/r.height;
  el.classList.add('tl');el.style.setProperty('--ry',((x-.5)*14).toFixed(2)+'deg');el.style.setProperty('--rx',((.5-y)*10).toFixed(2)+'deg');el.style.setProperty('--gx',(x*100).toFixed(1)+'%');el.style.setProperty('--gy',(y*100).toFixed(1)+'%')});
 document.addEventListener('pointerout',e=>{const el=e.target.closest&&e.target.closest(sel);if(el&&!el.contains(e.relatedTarget)){el.classList.remove('tl');el.style.removeProperty('--rx');el.style.removeProperty('--ry')}});
})();

/* sidebar: highlight the section being read */
(()=>{const a=[...document.querySelectorAll('#rail a[href^="#"]')],t=a.map(x=>document.querySelector(x.getAttribute('href')));
 const on=()=>{let k=-1;if($('#app').hidden){a.forEach(x=>x.classList.remove('on'));return}t.forEach((e,i)=>{if(e&&e.getBoundingClientRect().top<innerHeight*.4)k=i});a.forEach((x,i)=>x.classList.toggle('on',i==k))};
 addEventListener('scroll',on,{passive:true});on()})();

/* ---------- employee side panel ---------- */
function openEmp(id){DR=String(id);lastFocus=document.activeElement;$('#dscrim').hidden=false;$('#drawer').hidden=false;drawer();document.body.style.overflow='hidden';setTimeout(()=>{const x=$('#drawer .dx');x&&x.focus()},60)}
function closeEmp(){DR=null;$('#drawer').hidden=true;$('#dscrim').hidden=true;document.body.style.overflow='';if(lastFocus&&lastFocus.focus)lastFocus.focus()}
function drawer(){
 let r=ROWS.find(x=>String(x.e.id)==DR);
 if(!r){const l=ses.filter(s=>String(s.e.id)==DR);if(!l.length){closeEmp();return}r={e:l[0].e,l,q:stats(l),k:l[0].k}}
 const q=r.q,rt=rateOf(q),C=2*Math.PI*35,off=C*(1-(rt||0)/100),rc=rt==null?'#8ea0c4':rt>=95?'#4FD1A1':rt>=85?'#F0B429':'#FF6B5B';
 const ds=dates.filter(d=>F.from==null||(d>=F.from&&d<=F.to)),m=new Map(r.l.map(s=>[s.d,s]));
 let cal='',cur='',i=0;const wd=d=>(dd(d).getUTCDay()+6)%7;
 cal+=['Mon','Tue','Wed','Thu','Fri','Sat','Sun'].map(x=>`<span class="wd">${x}</span>`).join('');
 ds.forEach((d,ix)=>{const mo=dd(d).toLocaleDateString('en-GB',{month:'long',year:'numeric',timeZone:'UTC'});
  if(mo!==cur){cur=mo;cal+=`<div class="mo">${mo}</div>`+'<span class="c e"></span>'.repeat(wd(d))}
  const s=m.get(d),c=!s?'x':s.abs?'a':(s.late||s.noin||(!s.b&&!s.open))?'l':'p';
  cal+=`<span class="c ${c}" style="--i:${i++}" title="${dl(d)}: ${s?statusText(s):'No record'}">${dd(d).getUTCDate()}</span>`});
 const mc=(v,l,c)=>`<div><b class="${c||''}">${v}</b><small>${l}</small></div>`;
 const ln=r.l.slice().sort((a,b)=>b.a-a.a).map(s=>s.abs?`<div class="ln"><span>${dl(s.d)} <span class="pill ${s.k}">${NM[s.k]}</span></span><span class="bad">Absent | no punch</span></div>`:
  `<div class="ln"><span>${dl(s.d)} <span class="pill ${s.k}">${NM[s.k]}</span></span><span>${inS(s)} to ${outS(s)}${s.b&&!s.noin?' | '+hs(s.h*60):''}</span><span>${s.late?`<span class="pill bad">Late ${mn(s.late)}</span> `:''}${s.early?`<span class="pill bad">Left ${mn(s.early)} early</span> `:''}${s.ot?`<span class="pill good">+${mn(s.ot)} overtime</span>`:''}</span></div>`).join('');
 $('#dbody').innerHTML=`<div class="dh"><span class="av" style="--h:${hue(r.e.name)}">${esc((String(r.e.name).trim()[0]||'?').toUpperCase())}</span><div><h3>${esc(r.e.name)}</h3><small>ID ${esc(r.e.id)}${r.e.dept?' | '+esc(r.e.dept):''}</small><div style="margin-top:5px"><span class="stb ${stc(q)[0]}" style="margin:0">${stc(q)[1]}</span></div></div><button type="button" class="dx" aria-label="Close">&times;</button></div>
 <div class="sec"><div class="dsc"><div class="big"><svg viewBox="0 0 84 84"><circle class="t" cx="42" cy="42" r="35"/><circle class="v" style="--C:${C};--o:${off}" cx="42" cy="42" r="35" stroke="${rc}"/></svg><b>${rtxt(q)}</b></div>
 <div class="g3">${mc(q.n,'present')}${mc(q.abs,'absent',q.abs?'bad':'')}${mc(q.late,'late',q.late?'bad':'')}${mc(q.miss,'missing',q.miss?'bad':'')}${mc(q.avg?q.avg.toFixed(1):'-','avg hours')}${mc(hs(q.ot),'overtime')}</div></div></div>
 <div class="sec"><h4>Attendance calendar</h4><div class="cal">${cal}</div><div class="dleg"><span><i style="background:rgba(79,209,161,.7)"></i>Present</span><span><i style="background:rgba(240,180,41,.8)"></i>Late or missing</span><span><i style="background:rgba(255,100,80,.8)"></i>Absent</span><span><i style="background:rgba(255,255,255,.15)"></i>No record</span></div></div>
 <div class="dact"><button class="btn pri" data-x="pdf" data-id="${esc(r.e.id)}">Download PDF</button><button class="btn" data-x="xls" data-id="${esc(r.e.id)}">Download Excel</button>${asgSel({e:r.e})}</div>
 <div class="sec dtl"><h4>Day by day</h4>${ln}</div>`;
 $('#drawer .dx').onclick=closeEmp}
$('#dscrim').addEventListener('click',closeEmp);
addEventListener('keydown',e=>{if(e.key==='Escape'){if(DR!=null)closeEmp();else if(!$('#notif').hidden)$('#notif').hidden=true}});

/* ---------- on site now + shift countdown ---------- */
function nowCard(){const el=$('#now');if(!ses.length){el.hidden=true;return}
 const L=dates[dates.length-1],open=ses.filter(s=>s.d==L&&s.open&&(F.dept=='all'||s.e.dept==F.dept)),nw=new Date(),mm=nw.getHours()*60+nw.getMinutes();
 const st=[['m',P.m.s],['e',P.e.s%1440]].map(([k,s])=>[k,(s-mm+1440)%1440]).sort((a,b)=>a[1]-b[1])[0];
 const av=open.slice(0,7).map(s=>`<span class="av" style="--h:${hue(s.e.name)}" title="${esc(s.e.name)}">${esc((String(s.e.name).trim()[0]||'?').toUpperCase())}</span>`).join('')+(open.length>7?`<span class="mo">+${open.length-7}</span>`:'');
 el.innerHTML=`<div class="nt"><i>&#9201;</i><div><b>${NM[st[0]]} shift starts in ${mn(st[1]||1440)}</b><small>Based on your shift timings and the current time</small></div></div>`+
  (open.length?`<div class="nt"><div><span class="live">On site at the last punch</span><small>${open.length} ${open.length==1?'person has':'people have'} not punched out yet (${dlong(L)})</small></div></div><div class="av2">${av}</div>`:'');
 el.hidden=false}
setInterval(()=>{if(!$('#app').hidden)nowCard()},60000);

/* ---------- alerts bell ---------- */
function alerts(){
 const L=dates[dates.length-1],by=new Map();ses.forEach(s=>{if(F.dept!='all'&&s.e.dept!=F.dept)return;const k=String(s.e.id);if(!by.has(k))by.set(k,{e:s.e,l:[]});by.get(k).l.push(s)});
 const streak=[];by.forEach(v=>{v.l.sort((a,b)=>b.d-a.d);let n=0;for(const s of v.l){if(s.abs)n++;else break}if(n>=3)streak.push([v.e,n])});streak.sort((a,b)=>b[1]-a[1]);
 const lt=ses.filter(s=>s.d==L&&s.late&&(F.dept=='all'||s.e.dept==F.dept)).length,ms=ses.filter(s=>s.d==L&&(s.noin||(!s.b&&!s.open)&&!s.abs)&&(F.dept=='all'||s.e.dept==F.dept)).length;
 const A=[];streak.slice(0,5).forEach(([e,n])=>A.push(['r','&#9888;',`${esc(e.name)} absent ${n} days in a row`,'Tap to open',e.id]));
 if(lt)A.push(['o','&#9201;',`${lt} late arrival${lt>1?'s':''} on ${dl(L)}`,'Latest day in the file',null]);
 if(ms)A.push(['b','&#10067;',`${ms} missing punch${ms>1?'es':''} on ${dl(L)}`,'No in or out punch recorded',null]);
 const n=streak.length+(lt?1:0)+(ms?1:0),b=$('#bellN');b.hidden=!n;b.textContent=n>99?'99+':n;
 $('#notl').innerHTML=A.length?A.map(a=>`<button type="button" class="al ${a[0]}" ${a[4]!=null?`data-f="${esc(a[4])}"`:''}><i>${a[1]}</i><div><b>${a[2]}</b>${a[3]}</div></button>`).join(''):'<div class="none">All clear. No alerts for the latest day.</div>'}
$('#bell').addEventListener('click',e=>{e.stopPropagation();const n=$('#notif');n.hidden=!n.hidden;$('#bell').setAttribute('aria-expanded',!n.hidden)});
$('#notx').addEventListener('click',()=>$('#notif').hidden=true);
document.addEventListener('click',e=>{const n=$('#notif');if(!n.hidden&&!n.contains(e.target)&&!e.target.closest('#bell'))n.hidden=true;if(e.target.closest('#notl [data-f]'))n.hidden=true});

/* ---------- idle sign-out (5 minutes) ---------- */
(()=>{let last=Date.now(),ping=Date.now(),warned=false;const W=60;
 const act=()=>{if(warned)return;last=Date.now()};
 ['pointerdown','pointermove','keydown','scroll','touchstart','wheel'].forEach(ev=>addEventListener(ev,act,{passive:true}));
 const keep=async()=>{ping=Date.now();try{const r=await fetch('api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({a:'ping',csrf:CSRF})});if(r.status==403)location.href='logout.php?idle=1'}catch(e){}};
 $('#idStay').onclick=()=>{warned=false;last=Date.now();$('#idlev').hidden=true;keep()};
 window.__idleTest=s=>{last=Date.now()-(IDLE-s)*1000};
 setInterval(()=>{const left=IDLE-Math.floor((Date.now()-last)/1000);
  if(!warned&&left>0&&Date.now()-ping>60000&&Date.now()-last<60000)keep();
  if(left<=W&&left>0){if(!warned){warned=true;$('#idlev').hidden=false;$('#idStay').focus()}$('#idN').textContent=left;$('#idBar').style.width=(left/W*100)+'%'}
  else if(left<=0){keep().then(()=>0);fetch('logout.php').finally(()=>location.href='login.php?idle=1')}},1000)})();

/* ---------- filter panel (button opens it, 5 s of no touch closes it) ---------- */
function fsum(){const c=[];if(F.shift!='all')c.push(NM[F.shift]);if(F.dept!='all')c.push(F.dept);
 if(F.from!=null){const L=dates[dates.length-1],n=L-F.from+1;c.push(F.to==L&&[7,14,30].includes(n)?'Last '+n+' days':F.from==F.to?dl(F.from):dl(F.from)+' to '+dl(F.to))}
 if(F.q)c.push('"'+F.q+'"');
 $('#fsum').innerHTML=c.length?c.map(x=>`<span class="on">${esc(x)}</span>`).join(''):'<span>No filters &middot; all dates</span>'}
(()=>{const pn=$('#fpanel'),bt=$('#fbtn'),tm=$('#ftm'),T=5000;let to=0,hov=false;
 const arm=()=>{clearTimeout(to);tm.style.transition='none';tm.style.transform='scaleX(1)';if(hov||pn.classList.contains('fh'))return;void tm.offsetWidth;tm.style.transition=RM?'none':'transform '+T+'ms linear';tm.style.transform='scaleX(0)';to=setTimeout(()=>{if(!hov)show(false)},T)};
 function show(o){pn.classList.toggle('fh',!o);bt.setAttribute('aria-expanded',o);clearTimeout(to);if(o){arm();const f=pn.querySelector('input,select,button');}}
 bt.addEventListener('click',()=>show(pn.classList.contains('fh')));
 pn.addEventListener('pointerenter',()=>{hov=true;clearTimeout(to);tm.style.transition='none';tm.style.transform='scaleX(1)'});
 pn.addEventListener('pointerleave',()=>{hov=false;arm()});
 ['input','change','click','keydown','focusin'].forEach(ev=>pn.addEventListener(ev,()=>{if(!pn.classList.contains('fh'))arm()}));
 pn.addEventListener('keydown',e=>{if(e.key==='Escape'){show(false);bt.focus()}});
 window.__fOpen=()=>!pn.classList.contains('fh')})();

/* ---- v13: top performers, department rings, Ctrl+K search ---- */
(function(){
 const avh=n=>`<span class="av" style="--h:${hue(n)}">${esc(String(n).split(' ').map(x=>x[0]||'').slice(0,2).join('').toUpperCase())}</span>`;
 function extra(){
  let box=$('#xtra');
  if(!ses.length){if(box)box.remove();return}
  if(!box){box=document.createElement('div');box.id='xtra';box.className='xtra';$('#kpis').insertAdjacentElement('afterend',box)}
  const rows=agg(fl()).filter(r=>r.q.n>=3).map(r=>({e:r.e,r:rateOf(r.q)})).filter(x=>x.r!=null).sort((a,b)=>b.r-a.r||a.e.name.localeCompare(b.e.name)).slice(0,3);
  const dm=new Map();fl().forEach(s=>{const d=s.e.dept||'-';if(!dm.has(d))dm.set(d,[]);dm.get(d).push(s)});
  const dp=[...dm].map(([d,l])=>({d,r:rateOf(stats(l))})).filter(x=>x.r!=null).sort((a,b)=>b.r-a.r).slice(0,4);
  const C=2*Math.PI*17;
  box.innerHTML=`<div class="card xc"><h3>Top on-time</h3>${rows.length?rows.map((x,i)=>`<button class="xr" data-e="${esc(x.e.id)}" type="button"><span class="md m${i+1}">${i+1}</span>${avh(x.e.name)}<span class="xn"><b>${esc(x.e.name)}</b><small>${esc(x.e.dept||'')}</small></span><span class="xp">${x.r.toFixed(0)}%</span></button>`).join(''):'<p class="sub2">Not enough data yet.</p>'}</div>`+
  `<div class="card xc"><h3>By department</h3>${dp.length?dp.map(x=>`<div class="xd"><svg viewBox="0 0 44 44" aria-hidden="true"><circle cx="22" cy="22" r="17" fill="none" stroke="var(--line)" stroke-width="5"/><circle cx="22" cy="22" r="17" fill="none" stroke="${x.r>=95?'var(--good)':x.r>=85?'var(--warn)':'var(--bad)'}" stroke-width="5" stroke-linecap="round" stroke-dasharray="${C}" stroke-dashoffset="${C*(1-x.r/100)}" transform="rotate(-90 22 22)"/></svg><span class="xn"><b>${esc(x.d)}</b><small>${x.r.toFixed(0)}% attendance</small></span></div>`).join(''):'<p class="sub2">No departments yet.</p>'}</div>`;
  box.querySelectorAll('.xr').forEach(b=>b.onclick=()=>openEmp(b.dataset.e));
 }
 const _r=render;render=function(){_r.apply(this,arguments);try{extra()}catch(e){}};try{extra()}catch(e){}
 // command search
 const ov=document.createElement('div');ov.id='cmdk';ov.hidden=true;ov.innerHTML='<div class="cmd" role="dialog" aria-modal="true" aria-label="Search"><input id="cmdq" type="text" placeholder="Search employees or pages..." autocomplete="off" aria-label="Search"><ul id="cmdr" role="listbox"></ul><div class="ch">Up/Down to move, Enter to open, Esc to close</div></div>';
 document.body.appendChild(ov);
 const q=ov.querySelector('#cmdq'),ul=ov.querySelector('#cmdr');let items=[],ix=0;
 const pages=[['Shifts','s-shifts'],['Trend and attention','s-trend'],['Heatmap','s-heat'],['Reports','s-rep'],['Employees','s-emp']];
 function build(){
  const s=q.value.trim().toLowerCase();items=[];
  if(!$('#app').hidden){
   agg(ses).filter(r=>!s||String(r.e.name).toLowerCase().includes(s)||String(r.e.id).toLowerCase().includes(s)).slice(0,6).forEach(r=>items.push({t:r.e.name,m:(r.e.dept||'')+' | ID '+r.e.id,h:r.e.name,f:()=>openEmp(r.e.id)}));
   pages.filter(p=>!s||p[0].toLowerCase().includes(s)).forEach(p=>items.push({t:'Go to '+p[0],m:'Page',h:p[0],f:()=>{const e=document.getElementById(p[1]);if(e)e.scrollIntoView({behavior:RM?'auto':'smooth'})}}));
  }
  if(!s||'alerts'.includes(s))items.push({t:'Open alerts',m:'Action',h:'Alerts',f:()=>{const b=$('#bell');b&&b.click()}});
  items=items.slice(0,9);ix=0;
  ul.innerHTML=items.length?items.map((x,i)=>`<li role="option" data-i="${i}" class="${i?'':'on'}">${avh(x.h)}<span class="xn"><b>${esc(x.t)}</b><small>${esc(x.m)}</small></span></li>`).join(''):'<li class="none">No match</li>';
 }
 function mv(d){if(!items.length)return;ix=(ix+d+items.length)%items.length;ul.querySelectorAll('li').forEach((l,i)=>l.classList.toggle('on',i==ix))}
 function go(i){const x=items[i];if(!x)return;shut();x.f()}
 let prev=null;function show(){prev=document.activeElement;ov.hidden=false;q.value='';build();q.focus()}
 function shut(){ov.hidden=true;if(prev&&prev.focus)prev.focus()}
 q.oninput=build;
 q.onkeydown=e=>{if(e.key==='ArrowDown'){e.preventDefault();mv(1)}else if(e.key==='ArrowUp'){e.preventDefault();mv(-1)}else if(e.key==='Enter'){e.preventDefault();go(ix)}};
 ul.onclick=e=>{const l=e.target.closest('li[data-i]');if(l)go(+l.dataset.i)};
 ov.onclick=e=>{if(e.target===ov)shut()};
 addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();ov.hidden?show():shut()}else if(e.key==='Escape'&&!ov.hidden)shut()});
 const rb=document.getElementById('rail');if(rb){const b=document.createElement('button');b.type='button';b.className='rbtn';b.id='cmdbtn';b.title='Search (Ctrl+K)';b.innerHTML='<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="6" fill="none" stroke="currentColor" stroke-width="2"/><path d="M16 16l4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><span class="rl">Search</span>';b.onclick=show;rb.insertBefore(b,rb.firstChild.nextSibling||null)}
})();
