/* A5 poster renderer. QR encoder: qrcode-generator 1.4.4 (MIT). */
(() => {
'use strict';
const cfg=window.udcQR;
const rgb=cfg.udc_qr_color.match(/[a-f0-9]{2}/gi).map(v=>parseInt(v,16)/255).map(v=>v<=0.04045?v/12.92:Math.pow((v+0.055)/1.055,2.4));
if(1.05/(0.2126*rgb[0]+0.7152*rgb[1]+0.0722*rgb[2]+0.05)<4.5) {const note=document.createElement('p');note.setAttribute('role','status');note.textContent=cfg.contrast;document.querySelector('.udc-qr-preview, .udc-qr-download')?.before(note);}
async function loadLogo() {
 const image = new Image();
 image.src = cfg.logo;
 await image.decode();
 return image;
}
// Share the request, while allowing a later download to retry a failed load.
let logoPromise;
function logo() {
 if (!logoPromise) logoPromise = loadLogo().catch(error => { logoPromise = null; throw error; });
 return logoPromise;
}
async function render(canvas, service) {
 const brand = await logo();
 if (!['tattoo','piercing'].includes(service)) throw Error('Invalid service');
 canvas.width=1748; canvas.height=2480;
 const ctx=canvas.getContext('2d'), bg=cfg.udc_qr_background, ink=cfg.udc_qr_text;
 ctx.fillStyle=bg; ctx.fillRect(0,0,1748,2480);
 ctx.strokeStyle=ink; ctx.lineWidth=3; ctx.strokeRect(95,95,1558,2290);
 const scale=Math.min(650/brand.naturalWidth,440/brand.naturalHeight);
 const width=brand.naturalWidth*scale, height=brand.naturalHeight*scale;
 ctx.drawImage(brand,(1748-width)/2,150+(440-height)/2,width,height);
 ctx.fillStyle=ink; ctx.textAlign='center';
 const heading='Remplissez notre formulaire';
 const subtitle=service==='tattoo'?'pour votre tatouage':'pour votre piercing';
 ctx.font='54px Georgia, serif';
 const headingAscent=ctx.measureText(heading).actualBoundingBoxAscent;
 ctx.font='bold 70px Georgia, serif';
 const subtitleDescent=ctx.measureText(subtitle).actualBoundingBoxDescent;
 const logoBottom=150+(440-height)/2+height;
 const qrTop=980, lineSpacing=100;
 const textHeight=headingAscent+lineSpacing+subtitleDescent;
 const baseline=logoBottom+(qrTop-logoBottom-textHeight)/2+headingAscent;
 ctx.font='54px Georgia, serif'; ctx.fillText(heading,874,baseline);
 ctx.font='bold 70px Georgia, serif'; ctx.fillText(subtitle,874,baseline+lineSpacing);
 const qr=qrcode(0,'M'); qr.addData(new URL(cfg['udc_qr_'+service+'_url']).href); qr.make();
 const count=qr.getModuleCount(), cell=Math.floor(1200/(count+8)), size=(count+8)*cell;
 const x=Math.floor((1748-size)/2), y=980;
 ctx.fillStyle='#ffffff'; ctx.fillRect(x,y,size,size);
 ctx.fillStyle=cfg.udc_qr_color;
 for(let row=0;row<count;row++) for(let col=0;col<count;col++) if(qr.isDark(row,col)) ctx.fillRect(x+(col+4)*cell,y+(row+4)*cell,cell,cell);
 ctx.fillStyle=ink; ctx.font='36px Georgia, serif'; ctx.fillText('Scannez le QR code avec votre téléphone',874,2280);
 return canvas;
}
function crc(bytes) { let n=0xffffffff; for(const b of bytes) {n^=b;for(let i=0;i<8;i++) n=(n>>>1)^((n&1)?0xedb88320:0);} return (n^0xffffffff)>>>0; }
async function png(canvas) {
 const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/png')); if(!blob) throw Error('PNG');
 const data=new Uint8Array(await blob.arrayBuffer()), chunk=new Uint8Array(21), view=new DataView(chunk.buffer);
 view.setUint32(0,9); chunk.set([112,72,89,115],4); view.setUint32(8,11811); view.setUint32(12,11811); chunk[16]=1; view.setUint32(17,crc(chunk.slice(4,17)));
 const pieces=[data.slice(0,8)]; let at=8; let inserted=false;
 while(at<data.length) { const len=new DataView(data.buffer,data.byteOffset+at,4).getUint32(0); const type=String.fromCharCode(...data.slice(at+4,at+8)); if(type!=='pHYs') pieces.push(data.slice(at,at+len+12)); if(type==='IHDR'&&!inserted){pieces.push(chunk); inserted=true;} at+=len+12; }
 return new Blob(pieces,{type:'image/png'});
}
for(const preview of document.querySelectorAll('.udc-qr-preview')) { render(preview,preview.dataset.service).catch(() => preview.replaceWith(document.createTextNode(cfg.error))); }
for(const button of document.querySelectorAll('.udc-qr-download')) button.addEventListener('click',async()=>{
 button.disabled=true; const status=button.nextElementSibling; status.textContent='';
 try {const image=await render(document.createElement('canvas'),button.dataset.service); const url=URL.createObjectURL(await png(image)); const a=document.createElement('a'); a.href=url; a.download='titanium-'+button.dataset.service+'-qr-a5.png'; a.click(); setTimeout(()=>URL.revokeObjectURL(url),60000);} catch(e) {status.textContent=cfg.error;} finally {button.disabled=false;}
});
})();
