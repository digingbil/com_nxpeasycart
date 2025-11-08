(function(){"use strict";/**
* @vue/shared v3.5.22
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/function os(e){const t=Object.create(null);for(const s of e.split(","))t[s]=1;return s=>s in t}const J={},gt=[],Te=()=>{},Qs=()=>!1,Nt=e=>e.charCodeAt(0)===111&&e.charCodeAt(1)===110&&(e.charCodeAt(2)>122||e.charCodeAt(2)<97),ls=e=>e.startsWith("onUpdate:"),ae=Object.assign,cs=(e,t)=>{const s=e.indexOf(t);s>-1&&e.splice(s,1)},Mr=Object.prototype.hasOwnProperty,W=(e,t)=>Mr.call(e,t),L=Array.isArray,_t=e=>Lt(e)==="[object Map]",Rr=e=>Lt(e)==="[object Set]",N=e=>typeof e=="function",ie=e=>typeof e=="string",rt=e=>typeof e=="symbol",ne=e=>e!==null&&typeof e=="object",Xs=e=>(ne(e)||N(e))&&N(e.then)&&N(e.catch),Fr=Object.prototype.toString,Lt=e=>Fr.call(e),Nr=e=>Lt(e).slice(8,-1),Lr=e=>Lt(e)==="[object Object]",as=e=>ie(e)&&e!=="NaN"&&e[0]!=="-"&&""+parseInt(e,10)===e,mt=os(",key,ref,ref_for,ref_key,onVnodeBeforeMount,onVnodeMounted,onVnodeBeforeUpdate,onVnodeUpdated,onVnodeBeforeUnmount,onVnodeUnmounted"),Dt=e=>{const t=Object.create(null);return s=>t[s]||(t[s]=e(s))},Dr=/-\w/g,We=Dt(e=>e.replace(Dr,t=>t.slice(1).toUpperCase())),Hr=/\B([A-Z])/g,Xe=Dt(e=>e.replace(Hr,"-$1").toLowerCase()),zs=Dt(e=>e.charAt(0).toUpperCase()+e.slice(1)),us=Dt(e=>e?`on${zs(e)}`:""),Be=(e,t)=>!Object.is(e,t),fs=(e,...t)=>{for(let s=0;s<e.length;s++)e[s](...t)},Zs=(e,t,s,n=!1)=>{Object.defineProperty(e,t,{configurable:!0,enumerable:!1,writable:n,value:s})},$r=e=>{const t=parseFloat(e);return isNaN(t)?e:t};let en;const Ht=()=>en||(en=typeof globalThis!="undefined"?globalThis:typeof self!="undefined"?self:typeof window!="undefined"?window:typeof global!="undefined"?global:{});function ds(e){if(L(e)){const t={};for(let s=0;s<e.length;s++){const n=e[s],r=ie(n)?Vr(n):ds(n);if(r)for(const i in r)t[i]=r[i]}return t}else if(ie(e)||ne(e))return e}const jr=/;(?![^(]*\))/g,Ur=/:([^]+)/,qr=/\/\*[^]*?\*\//g;function Vr(e){const t={};return e.replace(qr,"").split(jr).forEach(s=>{if(s){const n=s.split(Ur);n.length>1&&(t[n[0].trim()]=n[1].trim())}}),t}function ps(e){let t="";if(ie(e))t=e;else if(L(e))for(let s=0;s<e.length;s++){const n=ps(e[s]);n&&(t+=n+" ")}else if(ne(e))for(const s in e)e[s]&&(t+=s+" ");return t.trim()}const Wr=os("itemscope,allowfullscreen,formnovalidate,ismap,nomodule,novalidate,readonly");function tn(e){return!!e||e===""}/**
* @vue/reactivity v3.5.22
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/let ge;class Br{constructor(t=!1){this.detached=t,this._active=!0,this._on=0,this.effects=[],this.cleanups=[],this._isPaused=!1,this.parent=ge,!t&&ge&&(this.index=(ge.scopes||(ge.scopes=[])).push(this)-1)}get active(){return this._active}pause(){if(this._active){this._isPaused=!0;let t,s;if(this.scopes)for(t=0,s=this.scopes.length;t<s;t++)this.scopes[t].pause();for(t=0,s=this.effects.length;t<s;t++)this.effects[t].pause()}}resume(){if(this._active&&this._isPaused){this._isPaused=!1;let t,s;if(this.scopes)for(t=0,s=this.scopes.length;t<s;t++)this.scopes[t].resume();for(t=0,s=this.effects.length;t<s;t++)this.effects[t].resume()}}run(t){if(this._active){const s=ge;try{return ge=this,t()}finally{ge=s}}}on(){++this._on===1&&(this.prevScope=ge,ge=this)}off(){this._on>0&&--this._on===0&&(ge=this.prevScope,this.prevScope=void 0)}stop(t){if(this._active){this._active=!1;let s,n;for(s=0,n=this.effects.length;s<n;s++)this.effects[s].stop();for(this.effects.length=0,s=0,n=this.cleanups.length;s<n;s++)this.cleanups[s]();if(this.cleanups.length=0,this.scopes){for(s=0,n=this.scopes.length;s<n;s++)this.scopes[s].stop(!0);this.scopes.length=0}if(!this.detached&&this.parent&&!t){const r=this.parent.scopes.pop();r&&r!==this&&(this.parent.scopes[this.index]=r,r.index=this.index)}this.parent=void 0}}}function Kr(){return ge}let Q;const hs=new WeakSet;class sn{constructor(t){this.fn=t,this.deps=void 0,this.depsTail=void 0,this.flags=5,this.next=void 0,this.cleanup=void 0,this.scheduler=void 0,ge&&ge.active&&ge.effects.push(this)}pause(){this.flags|=64}resume(){this.flags&64&&(this.flags&=-65,hs.has(this)&&(hs.delete(this),this.trigger()))}notify(){this.flags&2&&!(this.flags&32)||this.flags&8||rn(this)}run(){if(!(this.flags&1))return this.fn();this.flags|=2,un(this),on(this);const t=Q,s=xe;Q=this,xe=!0;try{return this.fn()}finally{ln(this),Q=t,xe=s,this.flags&=-3}}stop(){if(this.flags&1){for(let t=this.deps;t;t=t.nextDep)bs(t);this.deps=this.depsTail=void 0,un(this),this.onStop&&this.onStop(),this.flags&=-2}}trigger(){this.flags&64?hs.add(this):this.scheduler?this.scheduler():this.runIfDirty()}runIfDirty(){ms(this)&&this.run()}get dirty(){return ms(this)}}let nn=0,bt,yt;function rn(e,t=!1){if(e.flags|=8,t){e.next=yt,yt=e;return}e.next=bt,bt=e}function gs(){nn++}function _s(){if(--nn>0)return;if(yt){let t=yt;for(yt=void 0;t;){const s=t.next;t.next=void 0,t.flags&=-9,t=s}}let e;for(;bt;){let t=bt;for(bt=void 0;t;){const s=t.next;if(t.next=void 0,t.flags&=-9,t.flags&1)try{t.trigger()}catch(n){e||(e=n)}t=s}}if(e)throw e}function on(e){for(let t=e.deps;t;t=t.nextDep)t.version=-1,t.prevActiveLink=t.dep.activeLink,t.dep.activeLink=t}function ln(e){let t,s=e.depsTail,n=s;for(;n;){const r=n.prevDep;n.version===-1?(n===s&&(s=r),bs(n),Yr(n)):t=n,n.dep.activeLink=n.prevActiveLink,n.prevActiveLink=void 0,n=r}e.deps=t,e.depsTail=s}function ms(e){for(let t=e.deps;t;t=t.nextDep)if(t.dep.version!==t.version||t.dep.computed&&(cn(t.dep.computed)||t.dep.version!==t.version))return!0;return!!e._dirty}function cn(e){if(e.flags&4&&!(e.flags&16)||(e.flags&=-17,e.globalVersion===vt)||(e.globalVersion=vt,!e.isSSR&&e.flags&128&&(!e.deps&&!e._dirty||!ms(e))))return;e.flags|=2;const t=e.dep,s=Q,n=xe;Q=e,xe=!0;try{on(e);const r=e.fn(e._value);(t.version===0||Be(r,e._value))&&(e.flags|=128,e._value=r,t.version++)}catch(r){throw t.version++,r}finally{Q=s,xe=n,ln(e),e.flags&=-3}}function bs(e,t=!1){const{dep:s,prevSub:n,nextSub:r}=e;if(n&&(n.nextSub=r,e.prevSub=void 0),r&&(r.prevSub=n,e.nextSub=void 0),s.subs===e&&(s.subs=n,!n&&s.computed)){s.computed.flags&=-5;for(let i=s.computed.deps;i;i=i.nextDep)bs(i,!0)}!t&&!--s.sc&&s.map&&s.map.delete(s.key)}function Yr(e){const{prevDep:t,nextDep:s}=e;t&&(t.nextDep=s,e.prevDep=void 0),s&&(s.prevDep=t,e.nextDep=void 0)}let xe=!0;const an=[];function Pe(){an.push(xe),xe=!1}function Ae(){const e=an.pop();xe=e===void 0?!0:e}function un(e){const{cleanup:t}=e;if(e.cleanup=void 0,t){const s=Q;Q=void 0;try{t()}finally{Q=s}}}let vt=0;class Gr{constructor(t,s){this.sub=t,this.dep=s,this.version=s.version,this.nextDep=this.prevDep=this.nextSub=this.prevSub=this.prevActiveLink=void 0}}class ys{constructor(t){this.computed=t,this.version=0,this.activeLink=void 0,this.subs=void 0,this.map=void 0,this.key=void 0,this.sc=0,this.__v_skip=!0}track(t){if(!Q||!xe||Q===this.computed)return;let s=this.activeLink;if(s===void 0||s.sub!==Q)s=this.activeLink=new Gr(Q,this),Q.deps?(s.prevDep=Q.depsTail,Q.depsTail.nextDep=s,Q.depsTail=s):Q.deps=Q.depsTail=s,fn(s);else if(s.version===-1&&(s.version=this.version,s.nextDep)){const n=s.nextDep;n.prevDep=s.prevDep,s.prevDep&&(s.prevDep.nextDep=n),s.prevDep=Q.depsTail,s.nextDep=void 0,Q.depsTail.nextDep=s,Q.depsTail=s,Q.deps===s&&(Q.deps=n)}return s}trigger(t){this.version++,vt++,this.notify(t)}notify(t){gs();try{for(let s=this.subs;s;s=s.prevSub)s.sub.notify()&&s.sub.dep.notify()}finally{_s()}}}function fn(e){if(e.dep.sc++,e.sub.flags&4){const t=e.dep.computed;if(t&&!e.dep.subs){t.flags|=20;for(let n=t.deps;n;n=n.nextDep)fn(n)}const s=e.dep.subs;s!==e&&(e.prevSub=s,s&&(s.nextSub=e)),e.dep.subs=e}}const vs=new WeakMap,ze=Symbol(""),xs=Symbol(""),xt=Symbol("");function ce(e,t,s){if(xe&&Q){let n=vs.get(e);n||vs.set(e,n=new Map);let r=n.get(s);r||(n.set(s,r=new ys),r.map=n,r.key=s),r.track()}}function $e(e,t,s,n,r,i){const o=vs.get(e);if(!o){vt++;return}const l=a=>{a&&a.trigger()};if(gs(),t==="clear")o.forEach(l);else{const a=L(e),d=a&&as(s);if(a&&s==="length"){const f=Number(n);o.forEach((h,v)=>{(v==="length"||v===xt||!rt(v)&&v>=f)&&l(h)})}else switch((s!==void 0||o.has(void 0))&&l(o.get(s)),d&&l(o.get(xt)),t){case"add":a?d&&l(o.get("length")):(l(o.get(ze)),_t(e)&&l(o.get(xs)));break;case"delete":a||(l(o.get(ze)),_t(e)&&l(o.get(xs)));break;case"set":_t(e)&&l(o.get(ze));break}}_s()}function it(e){const t=q(e);return t===e?t:(ce(t,"iterate",xt),we(e)?t:t.map(ue))}function ws(e){return ce(e=q(e),"iterate",xt),e}const Jr={__proto__:null,[Symbol.iterator](){return Ss(this,Symbol.iterator,ue)},concat(...e){return it(this).concat(...e.map(t=>L(t)?it(t):t))},entries(){return Ss(this,"entries",e=>(e[1]=ue(e[1]),e))},every(e,t){return je(this,"every",e,t,void 0,arguments)},filter(e,t){return je(this,"filter",e,t,s=>s.map(ue),arguments)},find(e,t){return je(this,"find",e,t,ue,arguments)},findIndex(e,t){return je(this,"findIndex",e,t,void 0,arguments)},findLast(e,t){return je(this,"findLast",e,t,ue,arguments)},findLastIndex(e,t){return je(this,"findLastIndex",e,t,void 0,arguments)},forEach(e,t){return je(this,"forEach",e,t,void 0,arguments)},includes(...e){return Cs(this,"includes",e)},indexOf(...e){return Cs(this,"indexOf",e)},join(e){return it(this).join(e)},lastIndexOf(...e){return Cs(this,"lastIndexOf",e)},map(e,t){return je(this,"map",e,t,void 0,arguments)},pop(){return wt(this,"pop")},push(...e){return wt(this,"push",e)},reduce(e,...t){return dn(this,"reduce",e,t)},reduceRight(e,...t){return dn(this,"reduceRight",e,t)},shift(){return wt(this,"shift")},some(e,t){return je(this,"some",e,t,void 0,arguments)},splice(...e){return wt(this,"splice",e)},toReversed(){return it(this).toReversed()},toSorted(e){return it(this).toSorted(e)},toSpliced(...e){return it(this).toSpliced(...e)},unshift(...e){return wt(this,"unshift",e)},values(){return Ss(this,"values",ue)}};function Ss(e,t,s){const n=ws(e),r=n[t]();return n!==e&&!we(e)&&(r._next=r.next,r.next=()=>{const i=r._next();return i.done||(i.value=s(i.value)),i}),r}const Qr=Array.prototype;function je(e,t,s,n,r,i){const o=ws(e),l=o!==e&&!we(e),a=o[t];if(a!==Qr[t]){const h=a.apply(e,i);return l?ue(h):h}let d=s;o!==e&&(l?d=function(h,v){return s.call(this,ue(h),v,e)}:s.length>2&&(d=function(h,v){return s.call(this,h,v,e)}));const f=a.call(o,d,n);return l&&r?r(f):f}function dn(e,t,s,n){const r=ws(e);let i=s;return r!==e&&(we(e)?s.length>3&&(i=function(o,l,a){return s.call(this,o,l,a,e)}):i=function(o,l,a){return s.call(this,o,ue(l),a,e)}),r[t](i,...n)}function Cs(e,t,s){const n=q(e);ce(n,"iterate",xt);const r=n[t](...s);return(r===-1||r===!1)&&As(s[0])?(s[0]=q(s[0]),n[t](...s)):r}function wt(e,t,s=[]){Pe(),gs();const n=q(e)[t].apply(e,s);return _s(),Ae(),n}const Xr=os("__proto__,__v_isRef,__isVue"),pn=new Set(Object.getOwnPropertyNames(Symbol).filter(e=>e!=="arguments"&&e!=="caller").map(e=>Symbol[e]).filter(rt));function zr(e){rt(e)||(e=String(e));const t=q(this);return ce(t,"has",e),t.hasOwnProperty(e)}class hn{constructor(t=!1,s=!1){this._isReadonly=t,this._isShallow=s}get(t,s,n){if(s==="__v_skip")return t.__v_skip;const r=this._isReadonly,i=this._isShallow;if(s==="__v_isReactive")return!r;if(s==="__v_isReadonly")return r;if(s==="__v_isShallow")return i;if(s==="__v_raw")return n===(r?i?vn:yn:i?bn:mn).get(t)||Object.getPrototypeOf(t)===Object.getPrototypeOf(n)?t:void 0;const o=L(t);if(!r){let a;if(o&&(a=Jr[s]))return a;if(s==="hasOwnProperty")return zr}const l=Reflect.get(t,s,le(t)?t:n);if((rt(s)?pn.has(s):Xr(s))||(r||ce(t,"get",s),i))return l;if(le(l)){const a=o&&as(s)?l:l.value;return r&&ne(a)?Ps(a):a}return ne(l)?r?Ps(l):Ie(l):l}}class gn extends hn{constructor(t=!1){super(!1,t)}set(t,s,n,r){let i=t[s];if(!this._isShallow){const a=Ze(i);if(!we(n)&&!Ze(n)&&(i=q(i),n=q(n)),!L(t)&&le(i)&&!le(n))return a||(i.value=n),!0}const o=L(t)&&as(s)?Number(s)<t.length:W(t,s),l=Reflect.set(t,s,n,le(t)?t:r);return t===q(r)&&(o?Be(n,i)&&$e(t,"set",s,n):$e(t,"add",s,n)),l}deleteProperty(t,s){const n=W(t,s);t[s];const r=Reflect.deleteProperty(t,s);return r&&n&&$e(t,"delete",s,void 0),r}has(t,s){const n=Reflect.has(t,s);return(!rt(s)||!pn.has(s))&&ce(t,"has",s),n}ownKeys(t){return ce(t,"iterate",L(t)?"length":ze),Reflect.ownKeys(t)}}class _n extends hn{constructor(t=!1){super(!0,t)}set(t,s){return!0}deleteProperty(t,s){return!0}}const Zr=new gn,ei=new _n,ti=new gn(!0),si=new _n(!0),Ts=e=>e,$t=e=>Reflect.getPrototypeOf(e);function ni(e,t,s){return function(...n){const r=this.__v_raw,i=q(r),o=_t(i),l=e==="entries"||e===Symbol.iterator&&o,a=e==="keys"&&o,d=r[e](...n),f=s?Ts:t?Is:ue;return!t&&ce(i,"iterate",a?xs:ze),{next(){const{value:h,done:v}=d.next();return v?{value:h,done:v}:{value:l?[f(h[0]),f(h[1])]:f(h),done:v}},[Symbol.iterator](){return this}}}}function jt(e){return function(...t){return e==="delete"?!1:e==="clear"?void 0:this}}function ri(e,t){const s={get(r){const i=this.__v_raw,o=q(i),l=q(r);e||(Be(r,l)&&ce(o,"get",r),ce(o,"get",l));const{has:a}=$t(o),d=t?Ts:e?Is:ue;if(a.call(o,r))return d(i.get(r));if(a.call(o,l))return d(i.get(l));i!==o&&i.get(r)},get size(){const r=this.__v_raw;return!e&&ce(q(r),"iterate",ze),r.size},has(r){const i=this.__v_raw,o=q(i),l=q(r);return e||(Be(r,l)&&ce(o,"has",r),ce(o,"has",l)),r===l?i.has(r):i.has(r)||i.has(l)},forEach(r,i){const o=this,l=o.__v_raw,a=q(l),d=t?Ts:e?Is:ue;return!e&&ce(a,"iterate",ze),l.forEach((f,h)=>r.call(i,d(f),d(h),o))}};return ae(s,e?{add:jt("add"),set:jt("set"),delete:jt("delete"),clear:jt("clear")}:{add(r){!t&&!we(r)&&!Ze(r)&&(r=q(r));const i=q(this);return $t(i).has.call(i,r)||(i.add(r),$e(i,"add",r,r)),this},set(r,i){!t&&!we(i)&&!Ze(i)&&(i=q(i));const o=q(this),{has:l,get:a}=$t(o);let d=l.call(o,r);d||(r=q(r),d=l.call(o,r));const f=a.call(o,r);return o.set(r,i),d?Be(i,f)&&$e(o,"set",r,i):$e(o,"add",r,i),this},delete(r){const i=q(this),{has:o,get:l}=$t(i);let a=o.call(i,r);a||(r=q(r),a=o.call(i,r)),l&&l.call(i,r);const d=i.delete(r);return a&&$e(i,"delete",r,void 0),d},clear(){const r=q(this),i=r.size!==0,o=r.clear();return i&&$e(r,"clear",void 0,void 0),o}}),["keys","values","entries",Symbol.iterator].forEach(r=>{s[r]=ni(r,e,t)}),s}function Ut(e,t){const s=ri(e,t);return(n,r,i)=>r==="__v_isReactive"?!e:r==="__v_isReadonly"?e:r==="__v_raw"?n:Reflect.get(W(s,r)&&r in n?s:n,r,i)}const ii={get:Ut(!1,!1)},oi={get:Ut(!1,!0)},li={get:Ut(!0,!1)},ci={get:Ut(!0,!0)},mn=new WeakMap,bn=new WeakMap,yn=new WeakMap,vn=new WeakMap;function ai(e){switch(e){case"Object":case"Array":return 1;case"Map":case"Set":case"WeakMap":case"WeakSet":return 2;default:return 0}}function ui(e){return e.__v_skip||!Object.isExtensible(e)?0:ai(Nr(e))}function Ie(e){return Ze(e)?e:qt(e,!1,Zr,ii,mn)}function fi(e){return qt(e,!1,ti,oi,bn)}function Ps(e){return qt(e,!0,ei,li,yn)}function dl(e){return qt(e,!0,si,ci,vn)}function qt(e,t,s,n,r){if(!ne(e)||e.__v_raw&&!(t&&e.__v_isReactive))return e;const i=ui(e);if(i===0)return e;const o=r.get(e);if(o)return o;const l=new Proxy(e,i===2?n:s);return r.set(e,l),l}function St(e){return Ze(e)?St(e.__v_raw):!!(e&&e.__v_isReactive)}function Ze(e){return!!(e&&e.__v_isReadonly)}function we(e){return!!(e&&e.__v_isShallow)}function As(e){return e?!!e.__v_raw:!1}function q(e){const t=e&&e.__v_raw;return t?q(t):e}function di(e){return!W(e,"__v_skip")&&Object.isExtensible(e)&&Zs(e,"__v_skip",!0),e}const ue=e=>ne(e)?Ie(e):e,Is=e=>ne(e)?Ps(e):e;function le(e){return e?e.__v_isRef===!0:!1}function Es(e){return pi(e,!1)}function pi(e,t){return le(e)?e:new hi(e,t)}class hi{constructor(t,s){this.dep=new ys,this.__v_isRef=!0,this.__v_isShallow=!1,this._rawValue=s?t:q(t),this._value=s?t:ue(t),this.__v_isShallow=s}get value(){return this.dep.track(),this._value}set value(t){const s=this._rawValue,n=this.__v_isShallow||we(t)||Ze(t);t=n?t:q(t),Be(t,s)&&(this._rawValue=t,this._value=n?t:ue(t),this.dep.trigger())}}function gi(e){return le(e)?e.value:e}const _i={get:(e,t,s)=>t==="__v_raw"?e:gi(Reflect.get(e,t,s)),set:(e,t,s,n)=>{const r=e[t];return le(r)&&!le(s)?(r.value=s,!0):Reflect.set(e,t,s,n)}};function xn(e){return St(e)?e:new Proxy(e,_i)}class mi{constructor(t,s,n){this.fn=t,this.setter=s,this._value=void 0,this.dep=new ys(this),this.__v_isRef=!0,this.deps=void 0,this.depsTail=void 0,this.flags=16,this.globalVersion=vt-1,this.next=void 0,this.effect=this,this.__v_isReadonly=!s,this.isSSR=n}notify(){if(this.flags|=16,!(this.flags&8)&&Q!==this)return rn(this,!0),!0}get value(){const t=this.dep.track();return cn(this),t&&(t.version=this.dep.version),this._value}set value(t){this.setter&&this.setter(t)}}function bi(e,t,s=!1){let n,r;return N(e)?n=e:(n=e.get,r=e.set),new mi(n,r,s)}const Vt={},Wt=new WeakMap;let et;function yi(e,t=!1,s=et){if(s){let n=Wt.get(s);n||Wt.set(s,n=[]),n.push(e)}}function vi(e,t,s=J){const{immediate:n,deep:r,once:i,scheduler:o,augmentJob:l,call:a}=s,d=P=>r?P:we(P)||r===!1||r===0?Ke(P,1):Ke(P);let f,h,v,S,A=!1,R=!1;if(le(e)?(h=()=>e.value,A=we(e)):St(e)?(h=()=>d(e),A=!0):L(e)?(R=!0,A=e.some(P=>St(P)||we(P)),h=()=>e.map(P=>{if(le(P))return P.value;if(St(P))return d(P);if(N(P))return a?a(P,2):P()})):N(e)?t?h=a?()=>a(e,2):e:h=()=>{if(v){Pe();try{v()}finally{Ae()}}const P=et;et=f;try{return a?a(e,3,[S]):e(S)}finally{et=P}}:h=Te,t&&r){const P=h,U=r===!0?1/0:r;h=()=>Ke(P(),U)}const Z=Kr(),F=()=>{f.stop(),Z&&Z.active&&cs(Z.effects,f)};if(i&&t){const P=t;t=(...U)=>{P(...U),F()}}let y=R?new Array(e.length).fill(Vt):Vt;const I=P=>{if(!(!(f.flags&1)||!f.dirty&&!P))if(t){const U=f.run();if(r||A||(R?U.some((re,B)=>Be(re,y[B])):Be(U,y))){v&&v();const re=et;et=f;try{const B=[U,y===Vt?void 0:R&&y[0]===Vt?[]:y,S];y=U,a?a(t,3,B):t(...B)}finally{et=re}}}else f.run()};return l&&l(I),f=new sn(h),f.scheduler=o?()=>o(I,!1):I,S=P=>yi(P,!1,f),v=f.onStop=()=>{const P=Wt.get(f);if(P){if(a)a(P,4);else for(const U of P)U();Wt.delete(f)}},t?n?I(!0):y=f.run():o?o(I.bind(null,!0),!0):f.run(),F.pause=f.pause.bind(f),F.resume=f.resume.bind(f),F.stop=F,F}function Ke(e,t=1/0,s){if(t<=0||!ne(e)||e.__v_skip||(s=s||new Map,(s.get(e)||0)>=t))return e;if(s.set(e,t),t--,le(e))Ke(e.value,t,s);else if(L(e))for(let n=0;n<e.length;n++)Ke(e[n],t,s);else if(Rr(e)||_t(e))e.forEach(n=>{Ke(n,t,s)});else if(Lr(e)){for(const n in e)Ke(e[n],t,s);for(const n of Object.getOwnPropertySymbols(e))Object.prototype.propertyIsEnumerable.call(e,n)&&Ke(e[n],t,s)}return e}/**
* @vue/runtime-core v3.5.22
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/const Ct=[];let ks=!1;function pl(e,...t){if(ks)return;ks=!0,Pe();const s=Ct.length?Ct[Ct.length-1].component:null,n=s&&s.appContext.config.warnHandler,r=xi();if(n)ot(n,s,11,[e+t.map(i=>{var o,l;return(l=(o=i.toString)==null?void 0:o.call(i))!=null?l:JSON.stringify(i)}).join(""),s&&s.proxy,r.map(({vnode:i})=>`at <${dr(s,i.type)}>`).join(`
`),r]);else{const i=[`[Vue warn]: ${e}`,...t];r.length&&i.push(`
`,...wi(r)),console.warn(...i)}Ae(),ks=!1}function xi(){let e=Ct[Ct.length-1];if(!e)return[];const t=[];for(;e;){const s=t[0];s&&s.vnode===e?s.recurseCount++:t.push({vnode:e,recurseCount:0});const n=e.component&&e.component.parent;e=n&&n.vnode}return t}function wi(e){const t=[];return e.forEach((s,n)=>{t.push(...n===0?[]:[`
`],...Si(s))}),t}function Si({vnode:e,recurseCount:t}){const s=t>0?`... (${t} recursive calls)`:"",n=e.component?e.component.parent==null:!1,r=` at <${dr(e.component,e.type,n)}`,i=">"+s;return e.props?[r,...Ci(e.props),i]:[r+i]}function Ci(e){const t=[],s=Object.keys(e);return s.slice(0,3).forEach(n=>{t.push(...wn(n,e[n]))}),s.length>3&&t.push(" ..."),t}function wn(e,t,s){return ie(t)?(t=JSON.stringify(t),s?t:[`${e}=${t}`]):typeof t=="number"||typeof t=="boolean"||t==null?s?t:[`${e}=${t}`]:le(t)?(t=wn(e,q(t.value),!0),s?t:[`${e}=Ref<`,t,">"]):N(t)?[`${e}=fn${t.name?`<${t.name}>`:""}`]:(t=q(t),s?t:[`${e}=`,t])}function ot(e,t,s,n){try{return n?e(...n):e()}catch(r){Bt(r,t,s)}}function Ee(e,t,s,n){if(N(e)){const r=ot(e,t,s,n);return r&&Xs(r)&&r.catch(i=>{Bt(i,t,s)}),r}if(L(e)){const r=[];for(let i=0;i<e.length;i++)r.push(Ee(e[i],t,s,n));return r}}function Bt(e,t,s,n=!0){const r=t?t.vnode:null,{errorHandler:i,throwUnhandledErrorInProduction:o}=t&&t.appContext.config||J;if(t){let l=t.parent;const a=t.proxy,d=`https://vuejs.org/error-reference/#runtime-${s}`;for(;l;){const f=l.ec;if(f){for(let h=0;h<f.length;h++)if(f[h](e,a,d)===!1)return}l=l.parent}if(i){Pe(),ot(i,null,10,[e,a,d]),Ae();return}}Ti(e,s,r,n,o)}function Ti(e,t,s,n=!0,r=!1){if(r)throw e;console.error(e)}const fe=[];let ke=-1;const lt=[];let Ye=null,ct=0;const Sn=Promise.resolve();let Kt=null;function Pi(e){const t=Kt||Sn;return e?t.then(this?e.bind(this):e):t}function Ai(e){let t=ke+1,s=fe.length;for(;t<s;){const n=t+s>>>1,r=fe[n],i=Tt(r);i<e||i===e&&r.flags&2?t=n+1:s=n}return t}function Os(e){if(!(e.flags&1)){const t=Tt(e),s=fe[fe.length-1];!s||!(e.flags&2)&&t>=Tt(s)?fe.push(e):fe.splice(Ai(t),0,e),e.flags|=1,Cn()}}function Cn(){Kt||(Kt=Sn.then(An))}function Ii(e){L(e)?lt.push(...e):Ye&&e.id===-1?Ye.splice(ct+1,0,e):e.flags&1||(lt.push(e),e.flags|=1),Cn()}function Tn(e,t,s=ke+1){for(;s<fe.length;s++){const n=fe[s];if(n&&n.flags&2){if(e&&n.id!==e.uid)continue;fe.splice(s,1),s--,n.flags&4&&(n.flags&=-2),n(),n.flags&4||(n.flags&=-2)}}}function Pn(e){if(lt.length){const t=[...new Set(lt)].sort((s,n)=>Tt(s)-Tt(n));if(lt.length=0,Ye){Ye.push(...t);return}for(Ye=t,ct=0;ct<Ye.length;ct++){const s=Ye[ct];s.flags&4&&(s.flags&=-2),s.flags&8||s(),s.flags&=-2}Ye=null,ct=0}}const Tt=e=>e.id==null?e.flags&2?-1:1/0:e.id;function An(e){try{for(ke=0;ke<fe.length;ke++){const t=fe[ke];t&&!(t.flags&8)&&(t.flags&4&&(t.flags&=-2),ot(t,t.i,t.i?15:14),t.flags&4||(t.flags&=-2))}}finally{for(;ke<fe.length;ke++){const t=fe[ke];t&&(t.flags&=-2)}ke=-1,fe.length=0,Pn(),Kt=null,(fe.length||lt.length)&&An()}}let Oe=null,In=null;function Yt(e){const t=Oe;return Oe=e,In=e&&e.type.__scopeId||null,t}function Ei(e,t=Oe,s){if(!t||e._n)return e;const n=(...r)=>{n._d&&ir(-1);const i=Yt(t);let o;try{o=e(...r)}finally{Yt(i),n._d&&ir(1)}return o};return n._n=!0,n._c=!0,n._d=!0,n}function tt(e,t,s,n){const r=e.dirs,i=t&&t.dirs;for(let o=0;o<r.length;o++){const l=r[o];i&&(l.oldValue=i[o].value);let a=l.dir[n];a&&(Pe(),Ee(a,s,8,[e.el,l,e,t]),Ae())}}const ki=Symbol("_vte"),Oi=e=>e.__isTeleport,Mi=Symbol("_leaveCb");function Ms(e,t){e.shapeFlag&6&&e.component?(e.transition=t,Ms(e.component.subTree,t)):e.shapeFlag&128?(e.ssContent.transition=t.clone(e.ssContent),e.ssFallback.transition=t.clone(e.ssFallback)):e.transition=t}function En(e){e.ids=[e.ids[0]+e.ids[2]+++"-",0,0]}const Gt=new WeakMap;function Pt(e,t,s,n,r=!1){if(L(e)){e.forEach((A,R)=>Pt(A,t&&(L(t)?t[R]:t),s,n,r));return}if(At(n)&&!r){n.shapeFlag&512&&n.type.__asyncResolved&&n.component.subTree.component&&Pt(e,t,s,n.component.subTree);return}const i=n.shapeFlag&4?Bs(n.component):n.el,o=r?null:i,{i:l,r:a}=e,d=t&&t.r,f=l.refs===J?l.refs={}:l.refs,h=l.setupState,v=q(h),S=h===J?Qs:A=>W(v,A);if(d!=null&&d!==a){if(kn(t),ie(d))f[d]=null,S(d)&&(h[d]=null);else if(le(d)){d.value=null;const A=t;A.k&&(f[A.k]=null)}}if(N(a))ot(a,l,12,[o,f]);else{const A=ie(a),R=le(a);if(A||R){const Z=()=>{if(e.f){const F=A?S(a)?h[a]:f[a]:a.value;if(r)L(F)&&cs(F,i);else if(L(F))F.includes(i)||F.push(i);else if(A)f[a]=[i],S(a)&&(h[a]=f[a]);else{const y=[i];a.value=y,e.k&&(f[e.k]=y)}}else A?(f[a]=o,S(a)&&(h[a]=o)):R&&(a.value=o,e.k&&(f[e.k]=o))};if(o){const F=()=>{Z(),Gt.delete(e)};F.id=-1,Gt.set(e,F),ye(F,s)}else kn(e),Z()}}}function kn(e){const t=Gt.get(e);t&&(t.flags|=8,Gt.delete(e))}Ht().requestIdleCallback,Ht().cancelIdleCallback;const At=e=>!!e.type.__asyncLoader,On=e=>e.type.__isKeepAlive;function Ri(e,t){Mn(e,"a",t)}function Fi(e,t){Mn(e,"da",t)}function Mn(e,t,s=pe){const n=e.__wdc||(e.__wdc=()=>{let r=s;for(;r;){if(r.isDeactivated)return;r=r.parent}return e()});if(Jt(t,n,s),s){let r=s.parent;for(;r&&r.parent;)On(r.parent.vnode)&&Ni(n,t,s,r),r=r.parent}}function Ni(e,t,s,n){const r=Jt(t,e,n,!0);Rn(()=>{cs(n[t],r)},s)}function Jt(e,t,s=pe,n=!1){if(s){const r=s[e]||(s[e]=[]),i=t.__weh||(t.__weh=(...o)=>{Pe();const l=Ot(s),a=Ee(t,s,e,o);return l(),Ae(),a});return n?r.unshift(i):r.push(i),i}}const Ue=e=>(t,s=pe)=>{(!Mt||e==="sp")&&Jt(e,(...n)=>t(...n),s)},Li=Ue("bm"),Di=Ue("m"),Hi=Ue("bu"),$i=Ue("u"),ji=Ue("bum"),Rn=Ue("um"),Ui=Ue("sp"),qi=Ue("rtg"),Vi=Ue("rtc");function Wi(e,t=pe){Jt("ec",e,t)}const Bi=Symbol.for("v-ndc"),Rs=e=>e?ar(e)?Bs(e):Rs(e.parent):null,It=ae(Object.create(null),{$:e=>e,$el:e=>e.vnode.el,$data:e=>e.data,$props:e=>e.props,$attrs:e=>e.attrs,$slots:e=>e.slots,$refs:e=>e.refs,$parent:e=>Rs(e.parent),$root:e=>Rs(e.root),$host:e=>e.ce,$emit:e=>e.emit,$options:e=>Dn(e),$forceUpdate:e=>e.f||(e.f=()=>{Os(e.update)}),$nextTick:e=>e.n||(e.n=Pi.bind(e.proxy)),$watch:e=>ho.bind(e)}),Fs=(e,t)=>e!==J&&!e.__isScriptSetup&&W(e,t),Ki={get({_:e},t){if(t==="__v_skip")return!0;const{ctx:s,setupState:n,data:r,props:i,accessCache:o,type:l,appContext:a}=e;let d;if(t[0]!=="$"){const S=o[t];if(S!==void 0)switch(S){case 1:return n[t];case 2:return r[t];case 4:return s[t];case 3:return i[t]}else{if(Fs(n,t))return o[t]=1,n[t];if(r!==J&&W(r,t))return o[t]=2,r[t];if((d=e.propsOptions[0])&&W(d,t))return o[t]=3,i[t];if(s!==J&&W(s,t))return o[t]=4,s[t];Ns&&(o[t]=0)}}const f=It[t];let h,v;if(f)return t==="$attrs"&&ce(e.attrs,"get",""),f(e);if((h=l.__cssModules)&&(h=h[t]))return h;if(s!==J&&W(s,t))return o[t]=4,s[t];if(v=a.config.globalProperties,W(v,t))return v[t]},set({_:e},t,s){const{data:n,setupState:r,ctx:i}=e;return Fs(r,t)?(r[t]=s,!0):n!==J&&W(n,t)?(n[t]=s,!0):W(e.props,t)||t[0]==="$"&&t.slice(1)in e?!1:(i[t]=s,!0)},has({_:{data:e,setupState:t,accessCache:s,ctx:n,appContext:r,propsOptions:i,type:o}},l){let a,d;return!!(s[l]||e!==J&&l[0]!=="$"&&W(e,l)||Fs(t,l)||(a=i[0])&&W(a,l)||W(n,l)||W(It,l)||W(r.config.globalProperties,l)||(d=o.__cssModules)&&d[l])},defineProperty(e,t,s){return s.get!=null?e._.accessCache[t]=0:W(s,"value")&&this.set(e,t,s.value,null),Reflect.defineProperty(e,t,s)}};function Fn(e){return L(e)?e.reduce((t,s)=>(t[s]=null,t),{}):e}let Ns=!0;function Yi(e){const t=Dn(e),s=e.proxy,n=e.ctx;Ns=!1,t.beforeCreate&&Nn(t.beforeCreate,e,"bc");const{data:r,computed:i,methods:o,watch:l,provide:a,inject:d,created:f,beforeMount:h,mounted:v,beforeUpdate:S,updated:A,activated:R,deactivated:Z,beforeDestroy:F,beforeUnmount:y,destroyed:I,unmounted:P,render:U,renderTracked:re,renderTriggered:B,errorCaptured:X,serverPrefetch:se,expose:H,inheritAttrs:he,components:E,directives:ee,filters:Se}=t;if(d&&Gi(d,n,null),o)for(const V in o){const K=o[V];N(K)&&(n[V]=K.bind(s))}if(r){const V=r.call(s,s);ne(V)&&(e.data=Ie(V))}if(Ns=!0,i)for(const V in i){const K=i[V],_e=N(K)?K.bind(s,s):N(K.get)?K.get.bind(s,s):Te,D=!N(K)&&N(K.set)?K.set.bind(s):Te,G=oe({get:_e,set:D});Object.defineProperty(n,V,{enumerable:!0,configurable:!0,get:()=>G.value,set:te=>G.value=te})}if(l)for(const V in l)Ln(l[V],n,s,V);if(a){const V=N(a)?a.call(s):a;Reflect.ownKeys(V).forEach(K=>{eo(K,V[K])})}f&&Nn(f,e,"c");function $(V,K){L(K)?K.forEach(_e=>V(_e.bind(s))):K&&V(K.bind(s))}if($(Li,h),$(Di,v),$(Hi,S),$($i,A),$(Ri,R),$(Fi,Z),$(Wi,X),$(Vi,re),$(qi,B),$(ji,y),$(Rn,P),$(Ui,se),L(H))if(H.length){const V=e.exposed||(e.exposed={});H.forEach(K=>{Object.defineProperty(V,K,{get:()=>s[K],set:_e=>s[K]=_e,enumerable:!0})})}else e.exposed||(e.exposed={});U&&e.render===Te&&(e.render=U),he!=null&&(e.inheritAttrs=he),E&&(e.components=E),ee&&(e.directives=ee),se&&En(e)}function Gi(e,t,s=Te){L(e)&&(e=Ls(e));for(const n in e){const r=e[n];let i;ne(r)?"default"in r?i=Xt(r.from||n,r.default,!0):i=Xt(r.from||n):i=Xt(r),le(i)?Object.defineProperty(t,n,{enumerable:!0,configurable:!0,get:()=>i.value,set:o=>i.value=o}):t[n]=i}}function Nn(e,t,s){Ee(L(e)?e.map(n=>n.bind(t.proxy)):e.bind(t.proxy),t,s)}function Ln(e,t,s,n){let r=n.includes(".")?er(s,n):()=>s[n];if(ie(e)){const i=t[e];N(i)&&ut(r,i)}else if(N(e))ut(r,e.bind(s));else if(ne(e))if(L(e))e.forEach(i=>Ln(i,t,s,n));else{const i=N(e.handler)?e.handler.bind(s):t[e.handler];N(i)&&ut(r,i,e)}}function Dn(e){const t=e.type,{mixins:s,extends:n}=t,{mixins:r,optionsCache:i,config:{optionMergeStrategies:o}}=e.appContext,l=i.get(t);let a;return l?a=l:!r.length&&!s&&!n?a=t:(a={},r.length&&r.forEach(d=>Qt(a,d,o,!0)),Qt(a,t,o)),ne(t)&&i.set(t,a),a}function Qt(e,t,s,n=!1){const{mixins:r,extends:i}=t;i&&Qt(e,i,s,!0),r&&r.forEach(o=>Qt(e,o,s,!0));for(const o in t)if(!(n&&o==="expose")){const l=Ji[o]||s&&s[o];e[o]=l?l(e[o],t[o]):t[o]}return e}const Ji={data:Hn,props:$n,emits:$n,methods:Et,computed:Et,beforeCreate:de,created:de,beforeMount:de,mounted:de,beforeUpdate:de,updated:de,beforeDestroy:de,beforeUnmount:de,destroyed:de,unmounted:de,activated:de,deactivated:de,errorCaptured:de,serverPrefetch:de,components:Et,directives:Et,watch:Xi,provide:Hn,inject:Qi};function Hn(e,t){return t?e?function(){return ae(N(e)?e.call(this,this):e,N(t)?t.call(this,this):t)}:t:e}function Qi(e,t){return Et(Ls(e),Ls(t))}function Ls(e){if(L(e)){const t={};for(let s=0;s<e.length;s++)t[e[s]]=e[s];return t}return e}function de(e,t){return e?[...new Set([].concat(e,t))]:t}function Et(e,t){return e?ae(Object.create(null),e,t):t}function $n(e,t){return e?L(e)&&L(t)?[...new Set([...e,...t])]:ae(Object.create(null),Fn(e),Fn(t!=null?t:{})):t}function Xi(e,t){if(!e)return t;if(!t)return e;const s=ae(Object.create(null),e);for(const n in t)s[n]=de(e[n],t[n]);return s}function jn(){return{app:null,config:{isNativeTag:Qs,performance:!1,globalProperties:{},optionMergeStrategies:{},errorHandler:void 0,warnHandler:void 0,compilerOptions:{}},mixins:[],components:{},directives:{},provides:Object.create(null),optionsCache:new WeakMap,propsCache:new WeakMap,emitsCache:new WeakMap}}let zi=0;function Zi(e,t){return function(n,r=null){N(n)||(n=ae({},n)),r!=null&&!ne(r)&&(r=null);const i=jn(),o=new WeakSet,l=[];let a=!1;const d=i.app={_uid:zi++,_component:n,_props:r,_container:null,_context:i,_instance:null,version:jo,get config(){return i.config},set config(f){},use(f,...h){return o.has(f)||(f&&N(f.install)?(o.add(f),f.install(d,...h)):N(f)&&(o.add(f),f(d,...h))),d},mixin(f){return i.mixins.includes(f)||i.mixins.push(f),d},component(f,h){return h?(i.components[f]=h,d):i.components[f]},directive(f,h){return h?(i.directives[f]=h,d):i.directives[f]},mount(f,h,v){if(!a){const S=d._ceVNode||nt(n,r);return S.appContext=i,v===!0?v="svg":v===!1&&(v=void 0),e(S,f,v),a=!0,d._container=f,f.__vue_app__=d,Bs(S.component)}},onUnmount(f){l.push(f)},unmount(){a&&(Ee(l,d._instance,16),e(null,d._container),delete d._container.__vue_app__)},provide(f,h){return i.provides[f]=h,d},runWithContext(f){const h=at;at=d;try{return f()}finally{at=h}}};return d}}let at=null;function eo(e,t){if(pe){let s=pe.provides;const n=pe.parent&&pe.parent.provides;n===s&&(s=pe.provides=Object.create(n)),s[e]=t}}function Xt(e,t,s=!1){const n=Oo();if(n||at){let r=at?at._context.provides:n?n.parent==null||n.ce?n.vnode.appContext&&n.vnode.appContext.provides:n.parent.provides:void 0;if(r&&e in r)return r[e];if(arguments.length>1)return s&&N(t)?t.call(n&&n.proxy):t}}const Un={},qn=()=>Object.create(Un),Vn=e=>Object.getPrototypeOf(e)===Un;function to(e,t,s,n=!1){const r={},i=qn();e.propsDefaults=Object.create(null),Wn(e,t,r,i);for(const o in e.propsOptions[0])o in r||(r[o]=void 0);s?e.props=n?r:fi(r):e.type.props?e.props=r:e.props=i,e.attrs=i}function so(e,t,s,n){const{props:r,attrs:i,vnode:{patchFlag:o}}=e,l=q(r),[a]=e.propsOptions;let d=!1;if((n||o>0)&&!(o&16)){if(o&8){const f=e.vnode.dynamicProps;for(let h=0;h<f.length;h++){let v=f[h];if(zt(e.emitsOptions,v))continue;const S=t[v];if(a)if(W(i,v))S!==i[v]&&(i[v]=S,d=!0);else{const A=We(v);r[A]=Ds(a,l,A,S,e,!1)}else S!==i[v]&&(i[v]=S,d=!0)}}}else{Wn(e,t,r,i)&&(d=!0);let f;for(const h in l)(!t||!W(t,h)&&((f=Xe(h))===h||!W(t,f)))&&(a?s&&(s[h]!==void 0||s[f]!==void 0)&&(r[h]=Ds(a,l,h,void 0,e,!0)):delete r[h]);if(i!==l)for(const h in i)(!t||!W(t,h))&&(delete i[h],d=!0)}d&&$e(e.attrs,"set","")}function Wn(e,t,s,n){const[r,i]=e.propsOptions;let o=!1,l;if(t)for(let a in t){if(mt(a))continue;const d=t[a];let f;r&&W(r,f=We(a))?!i||!i.includes(f)?s[f]=d:(l||(l={}))[f]=d:zt(e.emitsOptions,a)||(!(a in n)||d!==n[a])&&(n[a]=d,o=!0)}if(i){const a=q(s),d=l||J;for(let f=0;f<i.length;f++){const h=i[f];s[h]=Ds(r,a,h,d[h],e,!W(d,h))}}return o}function Ds(e,t,s,n,r,i){const o=e[s];if(o!=null){const l=W(o,"default");if(l&&n===void 0){const a=o.default;if(o.type!==Function&&!o.skipFactory&&N(a)){const{propsDefaults:d}=r;if(s in d)n=d[s];else{const f=Ot(r);n=d[s]=a.call(null,t),f()}}else n=a;r.ce&&r.ce._setProp(s,n)}o[0]&&(i&&!l?n=!1:o[1]&&(n===""||n===Xe(s))&&(n=!0))}return n}const no=new WeakMap;function Bn(e,t,s=!1){const n=s?no:t.propsCache,r=n.get(e);if(r)return r;const i=e.props,o={},l=[];let a=!1;if(!N(e)){const f=h=>{a=!0;const[v,S]=Bn(h,t,!0);ae(o,v),S&&l.push(...S)};!s&&t.mixins.length&&t.mixins.forEach(f),e.extends&&f(e.extends),e.mixins&&e.mixins.forEach(f)}if(!i&&!a)return ne(e)&&n.set(e,gt),gt;if(L(i))for(let f=0;f<i.length;f++){const h=We(i[f]);Kn(h)&&(o[h]=J)}else if(i)for(const f in i){const h=We(f);if(Kn(h)){const v=i[f],S=o[h]=L(v)||N(v)?{type:v}:ae({},v),A=S.type;let R=!1,Z=!0;if(L(A))for(let F=0;F<A.length;++F){const y=A[F],I=N(y)&&y.name;if(I==="Boolean"){R=!0;break}else I==="String"&&(Z=!1)}else R=N(A)&&A.name==="Boolean";S[0]=R,S[1]=Z,(R||W(S,"default"))&&l.push(h)}}const d=[o,l];return ne(e)&&n.set(e,d),d}function Kn(e){return e[0]!=="$"&&!mt(e)}const Hs=e=>e==="_"||e==="_ctx"||e==="$stable",$s=e=>L(e)?e.map(Me):[Me(e)],ro=(e,t,s)=>{if(t._n)return t;const n=Ei((...r)=>$s(t(...r)),s);return n._c=!1,n},Yn=(e,t,s)=>{const n=e._ctx;for(const r in e){if(Hs(r))continue;const i=e[r];if(N(i))t[r]=ro(r,i,n);else if(i!=null){const o=$s(i);t[r]=()=>o}}},Gn=(e,t)=>{const s=$s(t);e.slots.default=()=>s},Jn=(e,t,s)=>{for(const n in t)(s||!Hs(n))&&(e[n]=t[n])},io=(e,t,s)=>{const n=e.slots=qn();if(e.vnode.shapeFlag&32){const r=t._;r?(Jn(n,t,s),s&&Zs(n,"_",r,!0)):Yn(t,n)}else t&&Gn(e,t)},oo=(e,t,s)=>{const{vnode:n,slots:r}=e;let i=!0,o=J;if(n.shapeFlag&32){const l=t._;l?s&&l===1?i=!1:Jn(r,t,s):(i=!t.$stable,Yn(t,r)),o=t}else t&&(Gn(e,t),o={default:1});if(i)for(const l in r)!Hs(l)&&o[l]==null&&delete r[l]},ye=wo;function lo(e){return co(e)}function co(e,t){const s=Ht();s.__VUE__=!0;const{insert:n,remove:r,patchProp:i,createElement:o,createText:l,createComment:a,setText:d,setElementText:f,parentNode:h,nextSibling:v,setScopeId:S=Te,insertStaticContent:A}=e,R=(c,u,p,m=null,g=null,_=null,C=void 0,w=null,x=!!u.dynamicChildren)=>{if(c===u)return;c&&!kt(c,u)&&(m=is(c),te(c,g,_,!0),c=null),u.patchFlag===-2&&(x=!1,u.dynamicChildren=null);const{type:b,ref:O,shapeFlag:T}=u;switch(b){case Zt:Z(c,u,p,m);break;case ft:F(c,u,p,m);break;case Us:c==null&&y(u,p,m,C);break;case qe:E(c,u,p,m,g,_,C,w,x);break;default:T&1?U(c,u,p,m,g,_,C,w,x):T&6?ee(c,u,p,m,g,_,C,w,x):(T&64||T&128)&&b.process(c,u,p,m,g,_,C,w,x,Rt)}O!=null&&g?Pt(O,c&&c.ref,_,u||c,!u):O==null&&c&&c.ref!=null&&Pt(c.ref,null,_,c,!0)},Z=(c,u,p,m)=>{if(c==null)n(u.el=l(u.children),p,m);else{const g=u.el=c.el;u.children!==c.children&&d(g,u.children)}},F=(c,u,p,m)=>{c==null?n(u.el=a(u.children||""),p,m):u.el=c.el},y=(c,u,p,m)=>{[c.el,c.anchor]=A(c.children,u,p,m,c.el,c.anchor)},I=({el:c,anchor:u},p,m)=>{let g;for(;c&&c!==u;)g=v(c),n(c,p,m),c=g;n(u,p,m)},P=({el:c,anchor:u})=>{let p;for(;c&&c!==u;)p=v(c),r(c),c=p;r(u)},U=(c,u,p,m,g,_,C,w,x)=>{u.type==="svg"?C="svg":u.type==="math"&&(C="mathml"),c==null?re(u,p,m,g,_,C,w,x):se(c,u,g,_,C,w,x)},re=(c,u,p,m,g,_,C,w)=>{let x,b;const{props:O,shapeFlag:T,transition:k,dirs:M}=c;if(x=c.el=o(c.type,_,O&&O.is,O),T&8?f(x,c.children):T&16&&X(c.children,x,null,m,g,js(c,_),C,w),M&&tt(c,null,m,"created"),B(x,c,c.scopeId,C,m),O){for(const z in O)z!=="value"&&!mt(z)&&i(x,z,null,O[z],_,m);"value"in O&&i(x,"value",null,O.value,_),(b=O.onVnodeBeforeMount)&&Re(b,m,c)}M&&tt(c,null,m,"beforeMount");const j=ao(g,k);j&&k.beforeEnter(x),n(x,u,p),((b=O&&O.onVnodeMounted)||j||M)&&ye(()=>{b&&Re(b,m,c),j&&k.enter(x),M&&tt(c,null,m,"mounted")},g)},B=(c,u,p,m,g)=>{if(p&&S(c,p),m)for(let _=0;_<m.length;_++)S(c,m[_]);if(g){let _=g.subTree;if(u===_||rr(_.type)&&(_.ssContent===u||_.ssFallback===u)){const C=g.vnode;B(c,C,C.scopeId,C.slotScopeIds,g.parent)}}},X=(c,u,p,m,g,_,C,w,x=0)=>{for(let b=x;b<c.length;b++){const O=c[b]=w?Je(c[b]):Me(c[b]);R(null,O,u,p,m,g,_,C,w)}},se=(c,u,p,m,g,_,C)=>{const w=u.el=c.el;let{patchFlag:x,dynamicChildren:b,dirs:O}=u;x|=c.patchFlag&16;const T=c.props||J,k=u.props||J;let M;if(p&&st(p,!1),(M=k.onVnodeBeforeUpdate)&&Re(M,p,u,c),O&&tt(u,c,p,"beforeUpdate"),p&&st(p,!0),(T.innerHTML&&k.innerHTML==null||T.textContent&&k.textContent==null)&&f(w,""),b?H(c.dynamicChildren,b,w,p,m,js(u,g),_):C||K(c,u,w,null,p,m,js(u,g),_,!1),x>0){if(x&16)he(w,T,k,p,g);else if(x&2&&T.class!==k.class&&i(w,"class",null,k.class,g),x&4&&i(w,"style",T.style,k.style,g),x&8){const j=u.dynamicProps;for(let z=0;z<j.length;z++){const Y=j[z],me=T[Y],be=k[Y];(be!==me||Y==="value")&&i(w,Y,me,be,g,p)}}x&1&&c.children!==u.children&&f(w,u.children)}else!C&&b==null&&he(w,T,k,p,g);((M=k.onVnodeUpdated)||O)&&ye(()=>{M&&Re(M,p,u,c),O&&tt(u,c,p,"updated")},m)},H=(c,u,p,m,g,_,C)=>{for(let w=0;w<u.length;w++){const x=c[w],b=u[w],O=x.el&&(x.type===qe||!kt(x,b)||x.shapeFlag&198)?h(x.el):p;R(x,b,O,null,m,g,_,C,!0)}},he=(c,u,p,m,g)=>{if(u!==p){if(u!==J)for(const _ in u)!mt(_)&&!(_ in p)&&i(c,_,u[_],null,g,m);for(const _ in p){if(mt(_))continue;const C=p[_],w=u[_];C!==w&&_!=="value"&&i(c,_,w,C,g,m)}"value"in p&&i(c,"value",u.value,p.value,g)}},E=(c,u,p,m,g,_,C,w,x)=>{const b=u.el=c?c.el:l(""),O=u.anchor=c?c.anchor:l("");let{patchFlag:T,dynamicChildren:k,slotScopeIds:M}=u;M&&(w=w?w.concat(M):M),c==null?(n(b,p,m),n(O,p,m),X(u.children||[],p,O,g,_,C,w,x)):T>0&&T&64&&k&&c.dynamicChildren?(H(c.dynamicChildren,k,p,g,_,C,w),(u.key!=null||g&&u===g.subTree)&&Qn(c,u,!0)):K(c,u,p,O,g,_,C,w,x)},ee=(c,u,p,m,g,_,C,w,x)=>{u.slotScopeIds=w,c==null?u.shapeFlag&512?g.ctx.activate(u,p,m,C,x):Se(u,p,m,g,_,C,x):Ce(c,u,x)},Se=(c,u,p,m,g,_,C)=>{const w=c.component=ko(c,m,g);if(On(c)&&(w.ctx.renderer=Rt),Mo(w,!1,C),w.asyncDep){if(g&&g.registerDep(w,$,C),!c.el){const x=w.subTree=nt(ft);F(null,x,u,p),c.placeholder=x.el}}else $(w,c,u,p,g,_,C)},Ce=(c,u,p)=>{const m=u.component=c.component;if(vo(c,u,p))if(m.asyncDep&&!m.asyncResolved){V(m,u,p);return}else m.next=u,m.update();else u.el=c.el,m.vnode=u},$=(c,u,p,m,g,_,C)=>{const w=()=>{if(c.isMounted){let{next:T,bu:k,u:M,parent:j,vnode:z}=c;{const De=Xn(c);if(De){T&&(T.el=z.el,V(c,T,C)),De.asyncDep.then(()=>{c.isUnmounted||w()});return}}let Y=T,me;st(c,!1),T?(T.el=z.el,V(c,T,C)):T=z,k&&fs(k),(me=T.props&&T.props.onVnodeBeforeUpdate)&&Re(me,j,T,z),st(c,!0);const be=sr(c),Le=c.subTree;c.subTree=be,R(Le,be,h(Le.el),is(Le),c,g,_),T.el=be.el,Y===null&&xo(c,be.el),M&&ye(M,g),(me=T.props&&T.props.onVnodeUpdated)&&ye(()=>Re(me,j,T,z),g)}else{let T;const{el:k,props:M}=u,{bm:j,m:z,parent:Y,root:me,type:be}=c,Le=At(u);st(c,!1),j&&fs(j),!Le&&(T=M&&M.onVnodeBeforeMount)&&Re(T,Y,u),st(c,!0);{me.ce&&me.ce._def.shadowRoot!==!1&&me.ce._injectChildStyle(be);const De=c.subTree=sr(c);R(null,De,p,m,c,g,_),u.el=De.el}if(z&&ye(z,g),!Le&&(T=M&&M.onVnodeMounted)){const De=u;ye(()=>Re(T,Y,De),g)}(u.shapeFlag&256||Y&&At(Y.vnode)&&Y.vnode.shapeFlag&256)&&c.a&&ye(c.a,g),c.isMounted=!0,u=p=m=null}};c.scope.on();const x=c.effect=new sn(w);c.scope.off();const b=c.update=x.run.bind(x),O=c.job=x.runIfDirty.bind(x);O.i=c,O.id=c.uid,x.scheduler=()=>Os(O),st(c,!0),b()},V=(c,u,p)=>{u.component=c;const m=c.vnode.props;c.vnode=u,c.next=null,so(c,u.props,m,p),oo(c,u.children,p),Pe(),Tn(c),Ae()},K=(c,u,p,m,g,_,C,w,x=!1)=>{const b=c&&c.children,O=c?c.shapeFlag:0,T=u.children,{patchFlag:k,shapeFlag:M}=u;if(k>0){if(k&128){D(b,T,p,m,g,_,C,w,x);return}else if(k&256){_e(b,T,p,m,g,_,C,w,x);return}}M&8?(O&16&&Qe(b,g,_),T!==b&&f(p,T)):O&16?M&16?D(b,T,p,m,g,_,C,w,x):Qe(b,g,_,!0):(O&8&&f(p,""),M&16&&X(T,p,m,g,_,C,w,x))},_e=(c,u,p,m,g,_,C,w,x)=>{c=c||gt,u=u||gt;const b=c.length,O=u.length,T=Math.min(b,O);let k;for(k=0;k<T;k++){const M=u[k]=x?Je(u[k]):Me(u[k]);R(c[k],M,p,null,g,_,C,w,x)}b>O?Qe(c,g,_,!0,!1,T):X(u,p,m,g,_,C,w,x,T)},D=(c,u,p,m,g,_,C,w,x)=>{let b=0;const O=u.length;let T=c.length-1,k=O-1;for(;b<=T&&b<=k;){const M=c[b],j=u[b]=x?Je(u[b]):Me(u[b]);if(kt(M,j))R(M,j,p,null,g,_,C,w,x);else break;b++}for(;b<=T&&b<=k;){const M=c[T],j=u[k]=x?Je(u[k]):Me(u[k]);if(kt(M,j))R(M,j,p,null,g,_,C,w,x);else break;T--,k--}if(b>T){if(b<=k){const M=k+1,j=M<O?u[M].el:m;for(;b<=k;)R(null,u[b]=x?Je(u[b]):Me(u[b]),p,j,g,_,C,w,x),b++}}else if(b>k)for(;b<=T;)te(c[b],g,_,!0),b++;else{const M=b,j=b,z=new Map;for(b=j;b<=k;b++){const ve=u[b]=x?Je(u[b]):Me(u[b]);ve.key!=null&&z.set(ve.key,b)}let Y,me=0;const be=k-j+1;let Le=!1,De=0;const Ft=new Array(be);for(b=0;b<be;b++)Ft[b]=0;for(b=M;b<=T;b++){const ve=c[b];if(me>=be){te(ve,g,_,!0);continue}let He;if(ve.key!=null)He=z.get(ve.key);else for(Y=j;Y<=k;Y++)if(Ft[Y-j]===0&&kt(ve,u[Y])){He=Y;break}He===void 0?te(ve,g,_,!0):(Ft[He-j]=b+1,He>=De?De=He:Le=!0,R(ve,u[He],p,null,g,_,C,w,x),me++)}const Er=Le?uo(Ft):gt;for(Y=Er.length-1,b=be-1;b>=0;b--){const ve=j+b,He=u[ve],kr=u[ve+1],Or=ve+1<O?kr.el||kr.placeholder:m;Ft[b]===0?R(null,He,p,Or,g,_,C,w,x):Le&&(Y<0||b!==Er[Y]?G(He,p,Or,2):Y--)}}},G=(c,u,p,m,g=null)=>{const{el:_,type:C,transition:w,children:x,shapeFlag:b}=c;if(b&6){G(c.component.subTree,u,p,m);return}if(b&128){c.suspense.move(u,p,m);return}if(b&64){C.move(c,u,p,Rt);return}if(C===qe){n(_,u,p);for(let T=0;T<x.length;T++)G(x[T],u,p,m);n(c.anchor,u,p);return}if(C===Us){I(c,u,p);return}if(m!==2&&b&1&&w)if(m===0)w.beforeEnter(_),n(_,u,p),ye(()=>w.enter(_),g);else{const{leave:T,delayLeave:k,afterLeave:M}=w,j=()=>{c.ctx.isUnmounted?r(_):n(_,u,p)},z=()=>{_._isLeaving&&_[Mi](!0),T(_,()=>{j(),M&&M()})};k?k(_,j,z):z()}else n(_,u,p)},te=(c,u,p,m=!1,g=!1)=>{const{type:_,props:C,ref:w,children:x,dynamicChildren:b,shapeFlag:O,patchFlag:T,dirs:k,cacheIndex:M}=c;if(T===-2&&(g=!1),w!=null&&(Pe(),Pt(w,null,p,c,!0),Ae()),M!=null&&(u.renderCache[M]=void 0),O&256){u.ctx.deactivate(c);return}const j=O&1&&k,z=!At(c);let Y;if(z&&(Y=C&&C.onVnodeBeforeUnmount)&&Re(Y,u,c),O&6)rs(c.component,p,m);else{if(O&128){c.suspense.unmount(p,m);return}j&&tt(c,null,u,"beforeUnmount"),O&64?c.type.remove(c,u,p,Rt,m):b&&!b.hasOnce&&(_!==qe||T>0&&T&64)?Qe(b,u,p,!1,!0):(_===qe&&T&384||!g&&O&16)&&Qe(x,u,p),m&&Ne(c)}(z&&(Y=C&&C.onVnodeUnmounted)||j)&&ye(()=>{Y&&Re(Y,u,c),j&&tt(c,null,u,"unmounted")},p)},Ne=c=>{const{type:u,el:p,anchor:m,transition:g}=c;if(u===qe){ns(p,m);return}if(u===Us){P(c);return}const _=()=>{r(p),g&&!g.persisted&&g.afterLeave&&g.afterLeave()};if(c.shapeFlag&1&&g&&!g.persisted){const{leave:C,delayLeave:w}=g,x=()=>C(p,_);w?w(c.el,_,x):x()}else _()},ns=(c,u)=>{let p;for(;c!==u;)p=v(c),r(c),c=p;r(u)},rs=(c,u,p)=>{const{bum:m,scope:g,job:_,subTree:C,um:w,m:x,a:b}=c;zn(x),zn(b),m&&fs(m),g.stop(),_&&(_.flags|=8,te(C,c,u,p)),w&&ye(w,u),ye(()=>{c.isUnmounted=!0},u)},Qe=(c,u,p,m=!1,g=!1,_=0)=>{for(let C=_;C<c.length;C++)te(c[C],u,p,m,g)},is=c=>{if(c.shapeFlag&6)return is(c.component.subTree);if(c.shapeFlag&128)return c.suspense.next();const u=v(c.anchor||c.el),p=u&&u[ki];return p?v(p):u};let Js=!1;const Ir=(c,u,p)=>{c==null?u._vnode&&te(u._vnode,null,null,!0):R(u._vnode||null,c,u,null,null,null,p),u._vnode=c,Js||(Js=!0,Tn(),Pn(),Js=!1)},Rt={p:R,um:te,m:G,r:Ne,mt:Se,mc:X,pc:K,pbc:H,n:is,o:e};return{render:Ir,hydrate:void 0,createApp:Zi(Ir)}}function js({type:e,props:t},s){return s==="svg"&&e==="foreignObject"||s==="mathml"&&e==="annotation-xml"&&t&&t.encoding&&t.encoding.includes("html")?void 0:s}function st({effect:e,job:t},s){s?(e.flags|=32,t.flags|=4):(e.flags&=-33,t.flags&=-5)}function ao(e,t){return(!e||e&&!e.pendingBranch)&&t&&!t.persisted}function Qn(e,t,s=!1){const n=e.children,r=t.children;if(L(n)&&L(r))for(let i=0;i<n.length;i++){const o=n[i];let l=r[i];l.shapeFlag&1&&!l.dynamicChildren&&((l.patchFlag<=0||l.patchFlag===32)&&(l=r[i]=Je(r[i]),l.el=o.el),!s&&l.patchFlag!==-2&&Qn(o,l)),l.type===Zt&&l.patchFlag!==-1&&(l.el=o.el),l.type===ft&&!l.el&&(l.el=o.el)}}function uo(e){const t=e.slice(),s=[0];let n,r,i,o,l;const a=e.length;for(n=0;n<a;n++){const d=e[n];if(d!==0){if(r=s[s.length-1],e[r]<d){t[n]=r,s.push(n);continue}for(i=0,o=s.length-1;i<o;)l=i+o>>1,e[s[l]]<d?i=l+1:o=l;d<e[s[i]]&&(i>0&&(t[n]=s[i-1]),s[i]=n)}}for(i=s.length,o=s[i-1];i-- >0;)s[i]=o,o=t[o];return s}function Xn(e){const t=e.subTree.component;if(t)return t.asyncDep&&!t.asyncResolved?t:Xn(t)}function zn(e){if(e)for(let t=0;t<e.length;t++)e[t].flags|=8}const fo=Symbol.for("v-scx"),po=()=>Xt(fo);function ut(e,t,s){return Zn(e,t,s)}function Zn(e,t,s=J){const{immediate:n,deep:r,flush:i,once:o}=s,l=ae({},s),a=t&&n||!t&&i!=="post";let d;if(Mt){if(i==="sync"){const S=po();d=S.__watcherHandles||(S.__watcherHandles=[])}else if(!a){const S=()=>{};return S.stop=Te,S.resume=Te,S.pause=Te,S}}const f=pe;l.call=(S,A,R)=>Ee(S,f,A,R);let h=!1;i==="post"?l.scheduler=S=>{ye(S,f&&f.suspense)}:i!=="sync"&&(h=!0,l.scheduler=(S,A)=>{A?S():Os(S)}),l.augmentJob=S=>{t&&(S.flags|=4),h&&(S.flags|=2,f&&(S.id=f.uid,S.i=f))};const v=vi(e,t,l);return Mt&&(d?d.push(v):a&&v()),v}function ho(e,t,s){const n=this.proxy,r=ie(e)?e.includes(".")?er(n,e):()=>n[e]:e.bind(n,n);let i;N(t)?i=t:(i=t.handler,s=t);const o=Ot(this),l=Zn(r,i.bind(n),s);return o(),l}function er(e,t){const s=t.split(".");return()=>{let n=e;for(let r=0;r<s.length&&n;r++)n=n[s[r]];return n}}const go=(e,t)=>t==="modelValue"||t==="model-value"?e.modelModifiers:e[`${t}Modifiers`]||e[`${We(t)}Modifiers`]||e[`${Xe(t)}Modifiers`];function _o(e,t,...s){if(e.isUnmounted)return;const n=e.vnode.props||J;let r=s;const i=t.startsWith("update:"),o=i&&go(n,t.slice(7));o&&(o.trim&&(r=s.map(f=>ie(f)?f.trim():f)),o.number&&(r=s.map($r)));let l,a=n[l=us(t)]||n[l=us(We(t))];!a&&i&&(a=n[l=us(Xe(t))]),a&&Ee(a,e,6,r);const d=n[l+"Once"];if(d){if(!e.emitted)e.emitted={};else if(e.emitted[l])return;e.emitted[l]=!0,Ee(d,e,6,r)}}const mo=new WeakMap;function tr(e,t,s=!1){const n=s?mo:t.emitsCache,r=n.get(e);if(r!==void 0)return r;const i=e.emits;let o={},l=!1;if(!N(e)){const a=d=>{const f=tr(d,t,!0);f&&(l=!0,ae(o,f))};!s&&t.mixins.length&&t.mixins.forEach(a),e.extends&&a(e.extends),e.mixins&&e.mixins.forEach(a)}return!i&&!l?(ne(e)&&n.set(e,null),null):(L(i)?i.forEach(a=>o[a]=null):ae(o,i),ne(e)&&n.set(e,o),o)}function zt(e,t){return!e||!Nt(t)?!1:(t=t.slice(2).replace(/Once$/,""),W(e,t[0].toLowerCase()+t.slice(1))||W(e,Xe(t))||W(e,t))}function hl(){}function sr(e){const{type:t,vnode:s,proxy:n,withProxy:r,propsOptions:[i],slots:o,attrs:l,emit:a,render:d,renderCache:f,props:h,data:v,setupState:S,ctx:A,inheritAttrs:R}=e,Z=Yt(e);let F,y;try{if(s.shapeFlag&4){const P=r||n,U=P;F=Me(d.call(U,P,f,h,S,v,A)),y=l}else{const P=t;F=Me(P.length>1?P(h,{attrs:l,slots:o,emit:a}):P(h,null)),y=t.props?l:bo(l)}}catch(P){Bt(P,e,1),F=nt(ft)}let I=F;if(y&&R!==!1){const P=Object.keys(y),{shapeFlag:U}=I;P.length&&U&7&&(i&&P.some(ls)&&(y=yo(y,i)),I=dt(I,y,!1,!0))}return s.dirs&&(I=dt(I,null,!1,!0),I.dirs=I.dirs?I.dirs.concat(s.dirs):s.dirs),s.transition&&Ms(I,s.transition),F=I,Yt(Z),F}const bo=e=>{let t;for(const s in e)(s==="class"||s==="style"||Nt(s))&&((t||(t={}))[s]=e[s]);return t},yo=(e,t)=>{const s={};for(const n in e)(!ls(n)||!(n.slice(9)in t))&&(s[n]=e[n]);return s};function vo(e,t,s){const{props:n,children:r,component:i}=e,{props:o,children:l,patchFlag:a}=t,d=i.emitsOptions;if(t.dirs||t.transition)return!0;if(s&&a>=0){if(a&1024)return!0;if(a&16)return n?nr(n,o,d):!!o;if(a&8){const f=t.dynamicProps;for(let h=0;h<f.length;h++){const v=f[h];if(o[v]!==n[v]&&!zt(d,v))return!0}}}else return(r||l)&&(!l||!l.$stable)?!0:n===o?!1:n?o?nr(n,o,d):!0:!!o;return!1}function nr(e,t,s){const n=Object.keys(t);if(n.length!==Object.keys(e).length)return!0;for(let r=0;r<n.length;r++){const i=n[r];if(t[i]!==e[i]&&!zt(s,i))return!0}return!1}function xo({vnode:e,parent:t},s){for(;t;){const n=t.subTree;if(n.suspense&&n.suspense.activeBranch===e&&(n.el=e.el),n===e)(e=t.vnode).el=s,t=t.parent;else break}}const rr=e=>e.__isSuspense;function wo(e,t){t&&t.pendingBranch?L(e)?t.effects.push(...e):t.effects.push(e):Ii(e)}const qe=Symbol.for("v-fgt"),Zt=Symbol.for("v-txt"),ft=Symbol.for("v-cmt"),Us=Symbol.for("v-stc");let Ge=null,qs=1;function ir(e,t=!1){qs+=e,e<0&&Ge&&t&&(Ge.hasOnce=!0)}function or(e){return e?e.__v_isVNode===!0:!1}function kt(e,t){return e.type===t.type&&e.key===t.key}const lr=({key:e})=>e!=null?e:null,es=({ref:e,ref_key:t,ref_for:s})=>(typeof e=="number"&&(e=""+e),e!=null?ie(e)||le(e)||N(e)?{i:Oe,r:e,k:t,f:!!s}:e:null);function So(e,t=null,s=null,n=0,r=null,i=e===qe?0:1,o=!1,l=!1){const a={__v_isVNode:!0,__v_skip:!0,type:e,props:t,key:t&&lr(t),ref:t&&es(t),scopeId:In,slotScopeIds:null,children:s,component:null,suspense:null,ssContent:null,ssFallback:null,dirs:null,transition:null,el:null,anchor:null,target:null,targetStart:null,targetAnchor:null,staticCount:0,shapeFlag:i,patchFlag:n,dynamicProps:r,dynamicChildren:null,appContext:null,ctx:Oe};return l?(Vs(a,s),i&128&&e.normalize(a)):s&&(a.shapeFlag|=ie(s)?8:16),qs>0&&!o&&Ge&&(a.patchFlag>0||i&6)&&a.patchFlag!==32&&Ge.push(a),a}const nt=Co;function Co(e,t=null,s=null,n=0,r=null,i=!1){if((!e||e===Bi)&&(e=ft),or(e)){const l=dt(e,t,!0);return s&&Vs(l,s),qs>0&&!i&&Ge&&(l.shapeFlag&6?Ge[Ge.indexOf(e)]=l:Ge.push(l)),l.patchFlag=-2,l}if($o(e)&&(e=e.__vccOpts),t){t=To(t);let{class:l,style:a}=t;l&&!ie(l)&&(t.class=ps(l)),ne(a)&&(As(a)&&!L(a)&&(a=ae({},a)),t.style=ds(a))}const o=ie(e)?1:rr(e)?128:Oi(e)?64:ne(e)?4:N(e)?2:0;return So(e,t,s,n,r,o,i,!0)}function To(e){return e?As(e)||Vn(e)?ae({},e):e:null}function dt(e,t,s=!1,n=!1){const{props:r,ref:i,patchFlag:o,children:l,transition:a}=e,d=t?Ao(r||{},t):r,f={__v_isVNode:!0,__v_skip:!0,type:e.type,props:d,key:d&&lr(d),ref:t&&t.ref?s&&i?L(i)?i.concat(es(t)):[i,es(t)]:es(t):i,scopeId:e.scopeId,slotScopeIds:e.slotScopeIds,children:l,target:e.target,targetStart:e.targetStart,targetAnchor:e.targetAnchor,staticCount:e.staticCount,shapeFlag:e.shapeFlag,patchFlag:t&&e.type!==qe?o===-1?16:o|16:o,dynamicProps:e.dynamicProps,dynamicChildren:e.dynamicChildren,appContext:e.appContext,dirs:e.dirs,transition:a,component:e.component,suspense:e.suspense,ssContent:e.ssContent&&dt(e.ssContent),ssFallback:e.ssFallback&&dt(e.ssFallback),placeholder:e.placeholder,el:e.el,anchor:e.anchor,ctx:e.ctx,ce:e.ce};return a&&n&&Ms(f,a.clone(f)),f}function Po(e=" ",t=0){return nt(Zt,null,e,t)}function Me(e){return e==null||typeof e=="boolean"?nt(ft):L(e)?nt(qe,null,e.slice()):or(e)?Je(e):nt(Zt,null,String(e))}function Je(e){return e.el===null&&e.patchFlag!==-1||e.memo?e:dt(e)}function Vs(e,t){let s=0;const{shapeFlag:n}=e;if(t==null)t=null;else if(L(t))s=16;else if(typeof t=="object")if(n&65){const r=t.default;r&&(r._c&&(r._d=!1),Vs(e,r()),r._c&&(r._d=!0));return}else{s=32;const r=t._;!r&&!Vn(t)?t._ctx=Oe:r===3&&Oe&&(Oe.slots._===1?t._=1:(t._=2,e.patchFlag|=1024))}else N(t)?(t={default:t,_ctx:Oe},s=32):(t=String(t),n&64?(s=16,t=[Po(t)]):s=8);e.children=t,e.shapeFlag|=s}function Ao(...e){const t={};for(let s=0;s<e.length;s++){const n=e[s];for(const r in n)if(r==="class")t.class!==n.class&&(t.class=ps([t.class,n.class]));else if(r==="style")t.style=ds([t.style,n.style]);else if(Nt(r)){const i=t[r],o=n[r];o&&i!==o&&!(L(i)&&i.includes(o))&&(t[r]=i?[].concat(i,o):o)}else r!==""&&(t[r]=n[r])}return t}function Re(e,t,s,n=null){Ee(e,t,7,[s,n])}const Io=jn();let Eo=0;function ko(e,t,s){const n=e.type,r=(t?t.appContext:e.appContext)||Io,i={uid:Eo++,vnode:e,type:n,parent:t,appContext:r,root:null,next:null,subTree:null,effect:null,update:null,job:null,scope:new Br(!0),render:null,proxy:null,exposed:null,exposeProxy:null,withProxy:null,provides:t?t.provides:Object.create(r.provides),ids:t?t.ids:["",0,0],accessCache:null,renderCache:[],components:null,directives:null,propsOptions:Bn(n,r),emitsOptions:tr(n,r),emit:null,emitted:null,propsDefaults:J,inheritAttrs:n.inheritAttrs,ctx:J,data:J,props:J,attrs:J,slots:J,refs:J,setupState:J,setupContext:null,suspense:s,suspenseId:s?s.pendingId:0,asyncDep:null,asyncResolved:!1,isMounted:!1,isUnmounted:!1,isDeactivated:!1,bc:null,c:null,bm:null,m:null,bu:null,u:null,um:null,bum:null,da:null,a:null,rtg:null,rtc:null,ec:null,sp:null};return i.ctx={_:i},i.root=t?t.root:i,i.emit=_o.bind(null,i),e.ce&&e.ce(i),i}let pe=null;const Oo=()=>pe||Oe;let ts,Ws;{const e=Ht(),t=(s,n)=>{let r;return(r=e[s])||(r=e[s]=[]),r.push(n),i=>{r.length>1?r.forEach(o=>o(i)):r[0](i)}};ts=t("__VUE_INSTANCE_SETTERS__",s=>pe=s),Ws=t("__VUE_SSR_SETTERS__",s=>Mt=s)}const Ot=e=>{const t=pe;return ts(e),e.scope.on(),()=>{e.scope.off(),ts(t)}},cr=()=>{pe&&pe.scope.off(),ts(null)};function ar(e){return e.vnode.shapeFlag&4}let Mt=!1;function Mo(e,t=!1,s=!1){t&&Ws(t);const{props:n,children:r}=e.vnode,i=ar(e);to(e,n,i,t),io(e,r,s||t);const o=i?Ro(e,t):void 0;return t&&Ws(!1),o}function Ro(e,t){const s=e.type;e.accessCache=Object.create(null),e.proxy=new Proxy(e.ctx,Ki);const{setup:n}=s;if(n){Pe();const r=e.setupContext=n.length>1?No(e):null,i=Ot(e),o=ot(n,e,0,[e.props,r]),l=Xs(o);if(Ae(),i(),(l||e.sp)&&!At(e)&&En(e),l){if(o.then(cr,cr),t)return o.then(a=>{ur(e,a)}).catch(a=>{Bt(a,e,0)});e.asyncDep=o}else ur(e,o)}else fr(e)}function ur(e,t,s){N(t)?e.type.__ssrInlineRender?e.ssrRender=t:e.render=t:ne(t)&&(e.setupState=xn(t)),fr(e)}function fr(e,t,s){const n=e.type;e.render||(e.render=n.render||Te);{const r=Ot(e);Pe();try{Yi(e)}finally{Ae(),r()}}}const Fo={get(e,t){return ce(e,"get",""),e[t]}};function No(e){const t=s=>{e.exposed=s||{}};return{attrs:new Proxy(e.attrs,Fo),slots:e.slots,emit:e.emit,expose:t}}function Bs(e){return e.exposed?e.exposeProxy||(e.exposeProxy=new Proxy(xn(di(e.exposed)),{get(t,s){if(s in t)return t[s];if(s in It)return It[s](e)},has(t,s){return s in t||s in It}})):e.proxy}const Lo=/(?:^|[-_])\w/g,Do=e=>e.replace(Lo,t=>t.toUpperCase()).replace(/[-_]/g,"");function Ho(e,t=!0){return N(e)?e.displayName||e.name:e.name||t&&e.__name}function dr(e,t,s=!1){let n=Ho(t);if(!n&&t.__file){const r=t.__file.match(/([^/\\]+)\.\w+$/);r&&(n=r[1])}if(!n&&e&&e.parent){const r=i=>{for(const o in i)if(i[o]===t)return o};n=r(e.components||e.parent.type.components)||r(e.appContext.components)}return n?Do(n):s?"App":"Anonymous"}function $o(e){return N(e)&&"__vccOpts"in e}const oe=(e,t)=>bi(e,t,Mt),jo="3.5.22";/**
* @vue/runtime-dom v3.5.22
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/let Ks;const pr=typeof window!="undefined"&&window.trustedTypes;if(pr)try{Ks=pr.createPolicy("vue",{createHTML:e=>e})}catch(e){}const hr=Ks?e=>Ks.createHTML(e):e=>e,Uo="http://www.w3.org/2000/svg",qo="http://www.w3.org/1998/Math/MathML",Ve=typeof document!="undefined"?document:null,gr=Ve&&Ve.createElement("template"),Vo={insert:(e,t,s)=>{t.insertBefore(e,s||null)},remove:e=>{const t=e.parentNode;t&&t.removeChild(e)},createElement:(e,t,s,n)=>{const r=t==="svg"?Ve.createElementNS(Uo,e):t==="mathml"?Ve.createElementNS(qo,e):s?Ve.createElement(e,{is:s}):Ve.createElement(e);return e==="select"&&n&&n.multiple!=null&&r.setAttribute("multiple",n.multiple),r},createText:e=>Ve.createTextNode(e),createComment:e=>Ve.createComment(e),setText:(e,t)=>{e.nodeValue=t},setElementText:(e,t)=>{e.textContent=t},parentNode:e=>e.parentNode,nextSibling:e=>e.nextSibling,querySelector:e=>Ve.querySelector(e),setScopeId(e,t){e.setAttribute(t,"")},insertStaticContent(e,t,s,n,r,i){const o=s?s.previousSibling:t.lastChild;if(r&&(r===i||r.nextSibling))for(;t.insertBefore(r.cloneNode(!0),s),!(r===i||!(r=r.nextSibling)););else{gr.innerHTML=hr(n==="svg"?`<svg>${e}</svg>`:n==="mathml"?`<math>${e}</math>`:e);const l=gr.content;if(n==="svg"||n==="mathml"){const a=l.firstChild;for(;a.firstChild;)l.appendChild(a.firstChild);l.removeChild(a)}t.insertBefore(l,s)}return[o?o.nextSibling:t.firstChild,s?s.previousSibling:t.lastChild]}},Wo=Symbol("_vtc");function Bo(e,t,s){const n=e[Wo];n&&(t=(t?[t,...n]:[...n]).join(" ")),t==null?e.removeAttribute("class"):s?e.setAttribute("class",t):e.className=t}const _r=Symbol("_vod"),Ko=Symbol("_vsh"),Yo=Symbol(""),Go=/(?:^|;)\s*display\s*:/;function Jo(e,t,s){const n=e.style,r=ie(s);let i=!1;if(s&&!r){if(t)if(ie(t))for(const o of t.split(";")){const l=o.slice(0,o.indexOf(":")).trim();s[l]==null&&ss(n,l,"")}else for(const o in t)s[o]==null&&ss(n,o,"");for(const o in s)o==="display"&&(i=!0),ss(n,o,s[o])}else if(r){if(t!==s){const o=n[Yo];o&&(s+=";"+o),n.cssText=s,i=Go.test(s)}}else t&&e.removeAttribute("style");_r in e&&(e[_r]=i?n.display:"",e[Ko]&&(n.display="none"))}const mr=/\s*!important$/;function ss(e,t,s){if(L(s))s.forEach(n=>ss(e,t,n));else if(s==null&&(s=""),t.startsWith("--"))e.setProperty(t,s);else{const n=Qo(e,t);mr.test(s)?e.setProperty(Xe(n),s.replace(mr,""),"important"):e[n]=s}}const br=["Webkit","Moz","ms"],Ys={};function Qo(e,t){const s=Ys[t];if(s)return s;let n=We(t);if(n!=="filter"&&n in e)return Ys[t]=n;n=zs(n);for(let r=0;r<br.length;r++){const i=br[r]+n;if(i in e)return Ys[t]=i}return t}const yr="http://www.w3.org/1999/xlink";function vr(e,t,s,n,r,i=Wr(t)){n&&t.startsWith("xlink:")?s==null?e.removeAttributeNS(yr,t.slice(6,t.length)):e.setAttributeNS(yr,t,s):s==null||i&&!tn(s)?e.removeAttribute(t):e.setAttribute(t,i?"":rt(s)?String(s):s)}function xr(e,t,s,n,r){if(t==="innerHTML"||t==="textContent"){s!=null&&(e[t]=t==="innerHTML"?hr(s):s);return}const i=e.tagName;if(t==="value"&&i!=="PROGRESS"&&!i.includes("-")){const l=i==="OPTION"?e.getAttribute("value")||"":e.value,a=s==null?e.type==="checkbox"?"on":"":String(s);(l!==a||!("_value"in e))&&(e.value=a),s==null&&e.removeAttribute(t),e._value=s;return}let o=!1;if(s===""||s==null){const l=typeof e[t];l==="boolean"?s=tn(s):s==null&&l==="string"?(s="",o=!0):l==="number"&&(s=0,o=!0)}try{e[t]=s}catch(l){}o&&e.removeAttribute(r||t)}function Xo(e,t,s,n){e.addEventListener(t,s,n)}function zo(e,t,s,n){e.removeEventListener(t,s,n)}const wr=Symbol("_vei");function Zo(e,t,s,n,r=null){const i=e[wr]||(e[wr]={}),o=i[t];if(n&&o)o.value=n;else{const[l,a]=el(t);if(n){const d=i[t]=nl(n,r);Xo(e,l,d,a)}else o&&(zo(e,l,o,a),i[t]=void 0)}}const Sr=/(?:Once|Passive|Capture)$/;function el(e){let t;if(Sr.test(e)){t={};let n;for(;n=e.match(Sr);)e=e.slice(0,e.length-n[0].length),t[n[0].toLowerCase()]=!0}return[e[2]===":"?e.slice(3):Xe(e.slice(2)),t]}let Gs=0;const tl=Promise.resolve(),sl=()=>Gs||(tl.then(()=>Gs=0),Gs=Date.now());function nl(e,t){const s=n=>{if(!n._vts)n._vts=Date.now();else if(n._vts<=s.attached)return;Ee(rl(n,s.value),t,5,[n])};return s.value=e,s.attached=sl(),s}function rl(e,t){if(L(t)){const s=e.stopImmediatePropagation;return e.stopImmediatePropagation=()=>{s.call(e),e._stopped=!0},t.map(n=>r=>!r._stopped&&n&&n(r))}else return t}const Cr=e=>e.charCodeAt(0)===111&&e.charCodeAt(1)===110&&e.charCodeAt(2)>96&&e.charCodeAt(2)<123,il=(e,t,s,n,r,i)=>{const o=r==="svg";t==="class"?Bo(e,n,o):t==="style"?Jo(e,s,n):Nt(t)?ls(t)||Zo(e,t,s,n,i):(t[0]==="."?(t=t.slice(1),!0):t[0]==="^"?(t=t.slice(1),!1):ol(e,t,n,o))?(xr(e,t,n),!e.tagName.includes("-")&&(t==="value"||t==="checked"||t==="selected")&&vr(e,t,n,o,i,t!=="value")):e._isVueCE&&(/[A-Z]/.test(t)||!ie(n))?xr(e,We(t),n,i,t):(t==="true-value"?e._trueValue=n:t==="false-value"&&(e._falseValue=n),vr(e,t,n,o))};function ol(e,t,s,n){if(n)return!!(t==="innerHTML"||t==="textContent"||t in e&&Cr(t)&&N(s));if(t==="spellcheck"||t==="draggable"||t==="translate"||t==="autocorrect"||t==="form"||t==="list"&&e.tagName==="INPUT"||t==="type"&&e.tagName==="TEXTAREA")return!1;if(t==="width"||t==="height"){const r=e.tagName;if(r==="IMG"||r==="VIDEO"||r==="CANVAS"||r==="SOURCE")return!1}return Cr(t)&&ie(s)?!1:t in e}const ll=ae({patchProp:il},Vo);let Tr;function cl(){return Tr||(Tr=lo(ll))}const pt=(...e)=>{const t=cl().createApp(...e),{mount:s}=t;return t.mount=n=>{const r=ul(n);if(!r)return;const i=t._component;!N(i)&&!i.render&&!i.template&&(i.template=r.innerHTML),r.nodeType===1&&(r.textContent="");const o=s(r,!1,al(r));return r instanceof Element&&(r.removeAttribute("v-cloak"),r.setAttribute("data-v-app","")),o},t};function al(e){if(e instanceof SVGElement)return"svg";if(typeof MathMLElement=="function"&&e instanceof MathMLElement)return"mathml"}function ul(e){return ie(e)?document.querySelector(e):e}const ht=(e,t)=>{const s=(e||0)/100;try{return new Intl.NumberFormat(void 0,{style:"currency",currency:t||"USD",minimumFractionDigits:2}).format(s)}catch(n){return`${t?`${t} `:""}${s.toFixed(2)}`}},Fe=(e,t={})=>{if(!e)return t;try{return JSON.parse(e)}catch(s){return console.warn("[NXP Easy Cart] Failed to parse island payload",s),t}},Pr={product:e=>{var S,A,R,Z,F,y,I,P,U,re,B,X,se;const t=Fe(e.dataset.nxpProduct,{}),s=t.product||{},r=(Array.isArray(t.variants)?t.variants:[]).map(H=>({...H,id:Number(H.id||0),stock:H.stock===null||H.stock===void 0?null:Number(H.stock)})).filter(H=>Number.isFinite(H.id)&&H.id>0),i={add_to_cart:((S=t.labels)==null?void 0:S.add_to_cart)||"Add to cart",select_variant:((A=t.labels)==null?void 0:A.select_variant)||"Select a variant",out_of_stock:((R=t.labels)==null?void 0:R.out_of_stock)||"Out of stock",added:((Z=t.labels)==null?void 0:Z.added)||"Added to cart",view_cart:((F=t.labels)==null?void 0:F.view_cart)||"View cart",qty_label:((y=t.labels)==null?void 0:y.qty_label)||"Quantity",error_generic:((I=t.labels)==null?void 0:I.error_generic)||"We couldn't add this item to your cart. Please try again.",variants_heading:((P=t.labels)==null?void 0:P.variants_heading)||"Variants",variant_sku:((U=t.labels)==null?void 0:U.variant_sku)||"SKU",variant_price:((re=t.labels)==null?void 0:re.variant_price)||"Price",variant_stock:((B=t.labels)==null?void 0:B.variant_stock)||"Stock",variant_options:((X=t.labels)==null?void 0:X.variant_options)||"Options",variant_none:((se=t.labels)==null?void 0:se.variant_none)||"—"},o=t.endpoints||{},l=t.links||{},a=t.token||"",d=Array.isArray(s.images)?s.images:[],f=d.length?d[0]:"",h=t.primary_alt||s.title||i.add_to_cart;e.innerHTML="",pt({template:`
      <div v-cloak>
        <div class="nxp-ec-product__media">
          <figure v-if="primaryImage" class="nxp-ec-product__figure">
            <img :src="primaryImage" :alt="primaryAlt" loading="lazy" />
          </figure>
        </div>

        <div class="nxp-ec-product__summary">
          <h1 class="nxp-ec-product__title">{{ product.title }}</h1>

          <ul
            v-if="product.categories && product.categories.length"
            class="nxp-ec-product__categories"
          >
            <li
              v-for="category in product.categories"
              :key="category.id || category.slug || category.title"
            >
              {{ category.title }}
            </li>
          </ul>

          <div v-if="displayPrice" class="nxp-ec-product__price">
            {{ displayPrice }}
          </div>

          <p v-if="product.short_desc" class="nxp-ec-product__intro">
            {{ product.short_desc }}
          </p>

          <div class="nxp-ec-product__actions" v-if="variants.length">
            <div
              v-if="variants.length > 1"
              class="nxp-ec-product__field"
            >
              <label :for="variantSelectId" class="nxp-ec-product__label">
                {{ labels.select_variant }}
              </label>
              <select
                :id="variantSelectId"
                class="nxp-ec-product__select"
                v-model.number="state.variantId"
              >
                <option value="">{{ labels.select_variant }}</option>
                <option
                  v-for="variant in variants"
                  :key="variant.id"
                  :value="variant.id"
                  :disabled="variant.stock !== null && variant.stock <= 0"
                >
                  {{ variant.sku }}
                  <template v-if="variant.price_label">
                    — {{ variant.price_label }}
                  </template>
                </option>
              </select>
            </div>

            <div class="nxp-ec-product__field">
              <label :for="qtyInputId" class="nxp-ec-product__label">
                {{ labels.qty_label }}
              </label>
              <input
                :id="qtyInputId"
                class="nxp-ec-product__qty-input"
                type="number"
                min="1"
                :max="maxQty"
                v-model.number="state.qty"
              />
            </div>

            <button
              type="button"
              class="nxp-ec-btn nxp-ec-btn--primary nxp-ec-product__buy"
              :disabled="isDisabled"
              @click="add"
            >
              <span
                v-if="state.loading"
                class="nxp-ec-product__spinner"
                aria-hidden="true"
              ></span>
              {{ labels.add_to_cart }}
            </button>

            <p
              v-if="isOutOfStock"
              class="nxp-ec-product__message nxp-ec-product__message--muted"
            >
              {{ labels.out_of_stock }}
            </p>

            <p
              v-if="state.error"
              class="nxp-ec-product__message nxp-ec-product__message--error"
            >
              {{ state.error }}
            </p>

            <p
              v-if="state.success"
              class="nxp-ec-product__message nxp-ec-product__message--success"
            >
              {{ state.successMessage || labels.added }}
              <template v-if="links.cart">
                · <a :href="links.cart">{{ labels.view_cart }}</a>
              </template>
            </p>
          </div>
        </div>

        <section
          v-if="product.long_desc_html"
          class="nxp-ec-product__description"
          v-html="product.long_desc_html"
        ></section>

        <section
          v-if="variants.length"
          class="nxp-ec-product__variants"
        >
          <h2 class="nxp-ec-product__variants-title">
            {{ labels.variants_heading }}
          </h2>

          <table class="nxp-ec-product__variants-table">
            <thead>
              <tr>
                <th scope="col">{{ labels.variant_sku }}</th>
                <th scope="col">{{ labels.variant_price }}</th>
                <th scope="col">{{ labels.variant_stock }}</th>
                <th scope="col">{{ labels.variant_options }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="variant in variants" :key="'row-' + variant.id">
                <td>{{ variant.sku }}</td>
                <td>{{ variant.price_label }}</td>
                <td>
                  <template v-if="variant.stock !== null">
                    {{ variant.stock }}
                  </template>
                  <template v-else>
                    {{ labels.variant_none }}
                  </template>
                </td>
                <td>
                  <ul
                    v-if="variant.options && variant.options.length"
                    class="nxp-ec-product__variant-options"
                  >
                    <li
                      v-for="(option, index) in variant.options"
                      :key="index"
                    >
                      <strong>{{ option.name }}:</strong>
                      {{ option.value }}
                    </li>
                  </ul>
                  <span
                    v-else
                    class="nxp-ec-product__variant-none"
                  >
                    {{ labels.variant_none }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </section>
      </div>
    `,setup(){const H=`nxp-ec-variant-${s.id||"0"}`,he=`nxp-ec-qty-${s.id||"0"}`,E=Ie({variantId:r.length===1?r[0].id:null,qty:1,loading:!1,success:!1,successMessage:"",error:""}),ee=oe(()=>r.length?E.variantId?r.find(D=>D.id===E.variantId)||null:r.length===1?r[0]:null:null),Se=oe(()=>{const D=ee.value;if(!D||D.stock===null||D.stock===void 0||!Number.isFinite(D.stock))return;const G=Number(D.stock);if(!(!Number.isFinite(G)||G<=0))return G}),Ce=D=>{let G=Number(D);(!Number.isFinite(G)||G<1)&&(G=1);const te=Se.value;return Number.isFinite(te)&&(G=Math.min(G,te)),G};ut(()=>E.qty,D=>{const G=Ce(D);G!==D&&(E.qty=G)}),ut(()=>E.variantId,()=>{E.error="",E.success=!1,E.successMessage="";const D=Ce(E.qty);D!==E.qty&&(E.qty=D)});const $=oe(()=>{var D;return ee.value&&ee.value.price_label?ee.value.price_label:((D=s.price)==null?void 0:D.label)||""}),V=oe(()=>{const D=ee.value;return!D||D.stock===null||D.stock===void 0?!1:Number(D.stock)<=0}),K=oe(()=>!!(E.loading||!r.length||!ee.value||V.value));return{product:s,variants:r,labels:i,links:l,primaryImage:f,primaryAlt:h,state:E,add:async()=>{var G;if(E.error="",E.success=!1,E.successMessage="",!o.add){E.error=i.error_generic;return}const D=ee.value;if(r.length&&!D){E.error=i.select_variant;return}if(V.value){E.error=i.out_of_stock;return}E.loading=!0;try{const te=new FormData;a&&te.append(a,"1"),te.append("product_id",String(s.id||"")),te.append("qty",String(Ce(E.qty))),D&&te.append("variant_id",String(D.id));let Ne=null;const ns=await fetch(o.add,{method:"POST",body:te,headers:{Accept:"application/json"}});try{Ne=await ns.json()}catch(Qe){}if(!ns.ok||!Ne||Ne.success===!1){const Qe=Ne&&Ne.message||i.error_generic;throw new Error(Qe)}const rs=((G=Ne.data)==null?void 0:G.cart)||null;E.success=!0,E.successMessage=Ne.message||i.added,rs&&window.dispatchEvent(new CustomEvent("nxp-cart:updated",{detail:rs}))}catch(te){E.error=te&&te.message||i.error_generic}finally{E.loading=!1}},displayPrice:$,isDisabled:K,isOutOfStock:V,maxQty:Se,variantSelectId:H,qtyInputId:he}}}).mount(e)},category:e=>{const t=Fe(e.dataset.nxpCategory,{}),s=Fe(e.dataset.nxpProducts,[]),n=Fe(e.dataset.nxpCategories,[]),r=Fe(e.dataset.nxpLabels,{}),i=Fe(e.dataset.nxpLinks,{}),o=(e.dataset.nxpSearch||"").trim(),l=y=>{if(y==null||y==="")return null;const I=Number.parseInt(y,10);return Number.isFinite(I)?I:null},a={filters:r.filters||"Categories",filter_all:r.filter_all||"All",empty:r.empty||"No products found in this category yet.",view_product:r.view_product||"View product",search_placeholder:r.search_placeholder||"Search products",search_label:r.search_label||r.search_placeholder||"Search products"},d={all:typeof i.all=="string"&&i.all!==""?i.all:"index.php?option=com_nxpeasycart&view=category",search:typeof i.search=="string"&&i.search!==""?i.search:"index.php?option=com_nxpeasycart&view=category"},f=Array.isArray(s)?s:[],h=Array.isArray(n)?n:[],v=t&&typeof t.slug=="string"?t.slug:"",S=`nxp-ec-category-search-${(t==null?void 0:t.id)||"all"}`,A=f.filter(y=>y&&typeof y=="object").map(y=>{const I=y.price&&typeof y.price=="object"?y.price:{},P=l(I.min_cents),U=l(I.max_cents),re=typeof I.currency=="string"&&I.currency!==""?I.currency:"USD";let B=typeof y.price_label=="string"?y.price_label:"";!B&&P!==null&&U!==null&&(P===U?B=ht(P,re):B=`${ht(P,re)} - ${ht(U,re)}`);const X=Array.isArray(y.images)?y.images.filter(se=>typeof se=="string"&&se.trim()!=="").map(se=>se.trim()):[];return{...y,title:typeof y.title=="string"?y.title:"",short_desc:typeof y.short_desc=="string"?y.short_desc:"",link:typeof y.link=="string"&&y.link!==""?y.link:"#",images:X,price:{currency:re,min_cents:P,max_cents:U},price_label:B}}),R=h.filter(y=>y&&typeof y=="object").map((y,I)=>({...y,title:typeof y.title=="string"&&y.title!==""?y.title:I===0?a.filter_all:"",slug:typeof y.slug=="string"?y.slug:"",link:typeof y.link=="string"&&y.link!==""?y.link:d.all})),Z=y=>{if(!(typeof window=="undefined"||!window.history||typeof window.history.replaceState!="function"))try{const I=new URL(window.location.href);y?I.searchParams.set("q",y):I.searchParams.delete("q"),window.history.replaceState({},"",I.toString())}catch(I){}};e.innerHTML="",pt({template:`
      <div class="nxp-ec-category" v-cloak>
        <header class="nxp-ec-category__header">
          <h1 class="nxp-ec-category__title">{{ title }}</h1>
          <form
            class="nxp-ec-category__search"
            method="get"
            :action="links.search"
            @submit.prevent="submitSearch"
          >
            <label class="sr-only" :for="searchId">{{ labels.search_label }}</label>
            <input
              :id="searchId"
              type="search"
              name="q"
              v-model="search"
              :placeholder="labels.search_placeholder"
            />
          </form>
          <nav
            v-if="filters.length"
            class="nxp-ec-category__filters"
            :aria-label="labels.filters"
          >
            <a
              v-for="filter in filters"
              :key="filter.slug || filter.id || filter.title"
              class="nxp-ec-category__filter"
              :class="{ 'is-active': isActive(filter) }"
              :href="filter.link"
            >
              {{ filter.title }}
            </a>
          </nav>
        </header>

        <p
          v-if="filteredProducts.length === 0"
          class="nxp-ec-category__empty"
        >
          {{ labels.empty }}
        </p>

        <div v-else class="nxp-ec-category__grid">
          <article
            v-for="product in filteredProducts"
            :key="product.id || product.slug || product.title"
            class="nxp-ec-product-card"
          >
            <figure v-if="product.images.length" class="nxp-ec-product-card__media">
              <img :src="product.images[0]" :alt="product.title" loading="lazy" />
            </figure>
            <div class="nxp-ec-product-card__body">
              <h2 class="nxp-ec-product-card__title">
                <a :href="product.link">{{ product.title }}</a>
              </h2>
              <p v-if="product.short_desc" class="nxp-ec-product-card__intro">
                {{ product.short_desc }}
              </p>
              <p v-if="product.price_label" class="nxp-ec-product-card__price">
                {{ product.price_label }}
              </p>
              <a class="nxp-ec-btn nxp-ec-btn--ghost" :href="product.link">
                {{ labels.view_product }}
              </a>
            </div>
          </article>
        </div>
      </div>
    `,setup(){const y=t&&typeof t.title=="string"&&t.title||"Products",I=Es(o),P=oe(()=>{const B=I.value.trim().toLowerCase();return B?A.filter(X=>`${X.title} ${X.short_desc||""}`.toLowerCase().includes(B)):A});return ut(I,(B,X)=>{const se=B.trim();X!==void 0&&X.trim()===se||Z(se)},{immediate:!0}),{title:y,search:I,searchId:S,filteredProducts:P,submitSearch:()=>{Z(I.value.trim())},labels:a,filters:R,links:d,isActive:B=>(typeof B.slug=="string"?B.slug:"")===v}}}).mount(e)},landing:e=>{var A,R;const t=Fe(e.dataset.nxpLanding,{}),s=t.hero||{},n=t.search||{},r=Array.isArray(t.categories)?t.categories:[],i=Array.isArray(t.sections)?t.sections:[],o=t.labels||{},l=t.trust||{},a=n.action||"index.php?option=com_nxpeasycart&view=category",d=n.placeholder||"Search for shoes, laptops, gifts…",f=((A=s==null?void 0:s.cta)==null?void 0:A.label)||"Shop Best Sellers",h=((R=s==null?void 0:s.cta)==null?void 0:R.link)||a,v={search_label:o.search_label||"Search the catalogue",search_button:o.search_button||"Search",view_all:o.view_all||"View all",view_product:o.view_product||"View product",categories_aria:o.categories_aria||"Browse categories"};e.innerHTML="",pt({template:`
      <div class="nxp-ec-landing__inner" v-cloak>
        <header class="nxp-ec-landing__hero">
          <div class="nxp-ec-landing__hero-copy">
            <p v-if="hero.eyebrow" class="nxp-ec-landing__eyebrow">{{ hero.eyebrow }}</p>
            <h1 class="nxp-ec-landing__title">{{ hero.title }}</h1>
            <p v-if="hero.subtitle" class="nxp-ec-landing__subtitle">{{ hero.subtitle }}</p>
            <div class="nxp-ec-landing__actions">
              <a class="nxp-ec-btn nxp-ec-btn--primary" :href="cta.link">
                {{ cta.label }}
              </a>
            </div>
          </div>
          <form class="nxp-ec-landing__search" @submit.prevent="submitSearch">
            <label class="sr-only" for="nxp-ec-landing-search-input">
              {{ labels.search_label }}
            </label>
            <input
              id="nxp-ec-landing-search-input"
              type="search"
              v-model="term"
              :placeholder="searchPlaceholder"
            />
            <button type="submit" class="nxp-ec-btn nxp-ec-btn--ghost">
              {{ labels.search_button }}
            </button>
          </form>
        </header>

        <section
          v-if="categoryTiles.length"
          class="nxp-ec-landing__categories"
          :aria-label="labels.categories_aria"
        >
          <a
            v-for="category in categoryTiles"
            :key="category.id || category.slug || category.title"
            class="nxp-ec-landing__category"
            :href="category.link"
          >
            <span class="nxp-ec-landing__category-title">{{ category.title }}</span>
          </a>
        </section>

        <section
          v-for="section in visibleSections"
          :key="section.key"
          class="nxp-ec-landing__section"
        >
          <header class="nxp-ec-landing__section-header">
            <h2 class="nxp-ec-landing__section-title">{{ section.title }}</h2>
            <a class="nxp-ec-landing__section-link" :href="searchAction">
              {{ labels.view_all }}
            </a>
          </header>
          <div class="nxp-ec-landing__grid">
            <article
              v-for="item in section.items"
              :key="item.id || item.slug || item.title"
              class="nxp-ec-landing__card"
            >
              <figure v-if="item.images && item.images.length" class="nxp-ec-landing__card-media">
                <img :src="item.images[0]" :alt="item.title" loading="lazy" />
              </figure>
              <div class="nxp-ec-landing__card-body">
                <h3 class="nxp-ec-landing__card-title">
                  <a :href="item.link">{{ item.title }}</a>
                </h3>
                <p v-if="item.short_desc" class="nxp-ec-landing__card-intro">
                  {{ item.short_desc }}
                </p>
                <p v-if="item.price_label" class="nxp-ec-landing__card-price">
                  {{ item.price_label }}
                </p>
                <a class="nxp-ec-btn nxp-ec-btn--ghost" :href="item.link">
                  {{ labels.view_product }}
                </a>
              </div>
            </article>
          </div>
        </section>

        <aside v-if="trust.text" class="nxp-ec-landing__trust">
          <p class="nxp-ec-landing__trust-text">{{ trust.text }}</p>
        </aside>
      </div>
    `,setup(){var X,se;const Z={eyebrow:s.eyebrow||"",title:s.title||"Shop",subtitle:s.subtitle||""},F={label:((X=s==null?void 0:s.cta)==null?void 0:X.label)||f,link:((se=s==null?void 0:s.cta)==null?void 0:se.link)||h},y=n.action||a,I=n.placeholder||d,P=i.filter(H=>Array.isArray(H.items)&&H.items.length),U=oe(()=>P.map(H=>({key:H.key||H.title,title:H.title||"",items:H.items.slice(0,12)}))),re=Es("");return{hero:Z,cta:F,term:re,submitSearch:()=>{const H=n.action||a,he=re.value.trim();try{const E=new URL(H,window.location.origin);he?E.searchParams.set("q",he):E.searchParams.delete("q"),window.location.href=E.toString()}catch(E){if(he){const ee=H.includes("?")?"&":"?";window.location.href=`${H}${ee}q=${encodeURIComponent(he)}`;return}window.location.href=H}},searchPlaceholder:I,searchAction:y,labels:v,categoryTiles:r,visibleSections:U,trust:typeof l.text=="string"?{text:l.text}:{text:""}}}}).mount(e)},cart:e=>{const t=Fe(e.dataset.nxpCart,{items:[],summary:{}});e.innerHTML="",pt({template:`
      <div class="nxp-ec-cart" v-cloak>
        <header class="nxp-ec-cart__header">
          <h1 class="nxp-ec-cart__title">Your cart</h1>
          <p class="nxp-ec-cart__lead">
            Review your items and proceed to checkout.
          </p>
        </header>

        <div v-if="items.length === 0" class="nxp-ec-cart__empty">
          <p>Your cart is currently empty.</p>
          <a class="nxp-ec-btn" href="index.php?option=com_nxpeasycart&view=category">
            Continue browsing
          </a>
        </div>

        <div v-else class="nxp-ec-cart__content">
          <table class="nxp-ec-cart__table">
            <thead>
              <tr>
                <th scope="col">Product</th>
                <th scope="col">Price</th>
                <th scope="col">Qty</th>
                <th scope="col">Total</th>
                <th scope="col" class="nxp-ec-cart__actions"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in items" :key="item.id">
                <td data-label="Product">
                  <strong>{{ item.product_title || item.title }}</strong>
                  <ul v-if="item.options && item.options.length" class="nxp-ec-cart__options">
                    <li v-for="(option, index) in item.options" :key="index">
                      <span>{{ option.name }}:</span> {{ option.value }}
                    </li>
                  </ul>
                </td>
                <td data-label="Price">{{ format(item.unit_price_cents) }}</td>
                <td data-label="Qty">
                  <input
                    type="number"
                    min="1"
                    :value="item.qty"
                    @input="updateQty(item, $event.target.value)"
                  />
                </td>
                <td data-label="Total">{{ format(item.total_cents) }}</td>
                <td class="nxp-ec-cart__actions">
                  <button type="button" class="nxp-ec-link-button" @click="remove(item)">
                    Remove
                  </button>
                </td>
              </tr>
            </tbody>
          </table>

          <aside class="nxp-ec-cart__summary">
            <h2>Summary</h2>
            <dl>
              <div>
                <dt>Subtotal</dt>
                <dd>{{ format(summary.subtotal_cents) }}</dd>
              </div>
              <div>
                <dt>Shipping</dt>
                <dd>Calculated at checkout</dd>
              </div>
              <div>
                <dt>Total</dt>
                <dd class="nxp-ec-cart__summary-total">{{ format(summary.total_cents) }}</dd>
              </div>
            </dl>

            <a class="nxp-ec-btn nxp-ec-btn--primary" href="index.php?option=com_nxpeasycart&view=checkout">
              Proceed to checkout
            </a>
          </aside>
        </div>
      </div>
    `,setup(){var d,f,h;const n=Ie(t.items||[]),r=((d=t.summary)==null?void 0:d.currency)||"USD",i=Ie({subtotal_cents:((f=t.summary)==null?void 0:f.subtotal_cents)||0,total_cents:((h=t.summary)==null?void 0:h.total_cents)||0}),o=()=>{const v=n.reduce((S,A)=>S+(A.total_cents||0),0);i.subtotal_cents=v,i.total_cents=v};return{items:n,summary:i,remove:v=>{const S=n.indexOf(v);S>=0&&(n.splice(S,1),o())},updateQty:(v,S)=>{const A=Math.max(1,parseInt(S,10)||1);v.qty=A,v.total_cents=A*(v.unit_price_cents||0),o()},format:v=>ht(v,r)}}}).mount(e)},"cart-summary":e=>{const t=Fe(e.dataset.nxpCartSummary,{}),s=t.labels||{},n=t.links||{};e.innerHTML="",pt({template:`
      <div class="nxp-ec-cart-summary__inner" v-cloak>
        <p v-if="state.count === 0" class="nxp-ec-cart-summary__empty">
          {{ labels.empty || "Your cart is empty." }}
        </p>
        <div v-else class="nxp-ec-cart-summary__content">
          <a :href="links.cart || '#'" class="nxp-ec-cart-summary__link">
            <span class="nxp-ec-cart-summary__count">{{ countLabel }}</span>
            <span class="nxp-ec-cart-summary__total">
              {{ (labels.total_label || "Total") + ": " + totalLabel }}
            </span>
          </a>
          <div class="nxp-ec-cart-summary__actions">
            <a
              v-if="links.cart"
              class="nxp-ec-btn nxp-ec-btn--ghost"
              :href="links.cart"
            >
              {{ labels.view_cart || "View cart" }}
            </a>
            <a
              v-if="links.checkout"
              class="nxp-ec-btn nxp-ec-btn--primary"
              :href="links.checkout"
            >
              {{ labels.checkout || "Checkout" }}
            </a>
          </div>
        </div>
      </div>
    `,setup(){const i=Ie({count:Number(t.count||0),total_cents:Number(t.total_cents||0),currency:t.currency||"USD"}),o=oe(()=>i.count===1?s.items_single||"1 item":(s.items_plural||"%d items").replace("%d",i.count)),l=oe(()=>ht(i.total_cents,i.currency||"USD")),a=d=>{var v,S;if(!d)return;const f=Array.isArray(d.items)?d.items:[];let h=0;f.forEach(A=>{h+=Number(A.qty||0)}),i.count=h,i.total_cents=Number(((v=d.summary)==null?void 0:v.total_cents)||i.total_cents),i.currency=((S=d.summary)==null?void 0:S.currency)||i.currency||"USD"};return window.addEventListener("nxp-cart:updated",d=>{a(d.detail)}),{state:i,labels:s,links:n,countLabel:o,totalLabel:l}}}).mount(e)},checkout:e=>{const t=Fe(e.dataset.nxpCheckout,{}),s=t.cart||{items:[],summary:{}},n=t.shipping_rules||[];t.tax_rates;const r=t.settings||{},i=t.payments||{},o=t.endpoints||{},l=t.token||"";e.innerHTML="",pt({template:`
      <div class="nxp-ec-checkout" v-cloak>
        <header class="nxp-ec-checkout__header">
          <h1 class="nxp-ec-checkout__title">Checkout</h1>
          <p class="nxp-ec-checkout__lead">
            Enter your details to complete the order.
          </p>
        </header>

        <div class="nxp-ec-checkout__layout" v-if="!success">
          <form class="nxp-ec-checkout__form" @submit.prevent="submit">
            <fieldset>
              <legend>Contact</legend>
              <div class="nxp-ec-checkout__field">
                <label for="nxp-ec-checkout-email">Email</label>
                <input id="nxp-ec-checkout-email" type="email" v-model="model.email" required />
              </div>
            </fieldset>

            <fieldset>
              <legend>Billing address</legend>
              <div class="nxp-ec-checkout__grid">
                <div class="nxp-ec-checkout__field">
                  <label for="nxp-ec-first-name">First name</label>
                  <input id="nxp-ec-first-name" type="text" v-model="model.billing.first_name" required />
                </div>
                <div class="nxp-ec-checkout__field">
                  <label for="nxp-ec-last-name">Last name</label>
                  <input id="nxp-ec-last-name" type="text" v-model="model.billing.last_name" required />
                </div>
                <div class="nxp-ec-checkout__field nxp-ec-checkout__field--wide">
                  <label for="nxp-ec-address-line1">Address</label>
                  <input id="nxp-ec-address-line1" type="text" v-model="model.billing.address_line1" required />
                </div>
                <div class="nxp-ec-checkout__field">
                  <label for="nxp-ec-city">City</label>
                  <input id="nxp-ec-city" type="text" v-model="model.billing.city" required />
                </div>
                <div class="nxp-ec-checkout__field">
                  <label for="nxp-ec-postcode">Postcode</label>
                  <input id="nxp-ec-postcode" type="text" v-model="model.billing.postcode" required />
                </div>
                <div class="nxp-ec-checkout__field">
                  <label for="nxp-ec-country">Country</label>
                  <input id="nxp-ec-country" type="text" v-model="model.billing.country" required />
                </div>
              </div>
            </fieldset>

            <fieldset>
              <legend>Shipping</legend>
              <p class="nxp-ec-checkout__radio-group">
                <label
                  v-for="rule in shippingRules"
                  :key="rule.id"
                >
                  <input
                    type="radio"
                    name="shipping_rule"
                    :value="rule.id"
                    v-model="model.shipping_rule_id"
                  />
                  <span>{{ rule.name }} — {{ formatMoney(rule.price_cents) }}</span>
                </label>
                <span v-if="shippingRules.length === 0">No shipping rules configured yet.</span>
              </p>
            </fieldset>

            <fieldset>
              <legend>Payment method</legend>
              <p class="nxp-ec-checkout__radio-group" v-if="gateways.length">
                <label
                  v-for="gateway in gateways"
                  :key="gateway.id"
                >
                  <input
                    type="radio"
                    name="nxp-ec-checkout-gateway"
                    :value="gateway.id"
                    v-model="selectedGateway"
                  />
                  <span>{{ gateway.label }}</span>
                </label>
              </p>
              <p v-else>
                Payments will be captured offline once this order is submitted.
              </p>
            </fieldset>

            <div v-if="error" class="nxp-ec-admin-alert nxp-ec-admin-alert--error">
              {{ error }}
            </div>

            <button type="submit" class="nxp-ec-btn nxp-ec-btn--primary" :disabled="loading">
              <span v-if="loading">Processing…</span>
              <span v-else>Complete order</span>
            </button>
          </form>

          <aside class="nxp-ec-checkout__summary">
            <h2>Order summary</h2>
            <div class="nxp-ec-checkout__cart" v-if="cartItems.length">
              <ul>
                <li v-for="item in cartItems" :key="item.id">
                  <div>
                    <strong>{{ item.product_title || item.title }}</strong>
                    <span class="nxp-ec-checkout__qty">× {{ item.qty }}</span>
                  </div>
                  <div class="nxp-ec-checkout__price">{{ formatMoney(item.total_cents) }}</div>
                </li>
              </ul>
              <div class="nxp-ec-checkout__totals">
                <div>
                  <span>Subtotal</span>
                  <strong>{{ formatMoney(subtotal) }}</strong>
                </div>
                <div>
                  <span>Shipping</span>
                  <strong>{{ formatMoney(selectedShippingCost) }}</strong>
                </div>
                <div>
                  <span>Total</span>
                  <strong>{{ formatMoney(total) }}</strong>
                </div>
              </div>
            </div>
            <p v-else>Your cart is empty.</p>
          </aside>
        </div>

        <div v-else class="nxp-ec-order-confirmation__summary">
          <h2>Thank you!</h2>
          <p>Your order <strong>{{ orderNumber }}</strong> was created successfully.</p>
          <a class="nxp-ec-btn" :href="orderUrl">View order summary</a>
        </div>
      </div>
    `,setup(){var B,X,se,H,he;const d=Ie((s.items||[]).map(E=>({...E}))),f=((B=s.summary)==null?void 0:B.currency)||r.base_currency||"USD",h=n.map((E,ee)=>({...E,price_cents:E.price_cents||0,default:ee===0})),v=(E,ee=[])=>ee.every(Se=>{var $;const Ce=($=E[Se])!=null?$:"";return String(Ce).trim()!==""}),S=[];v((X=i.stripe)!=null?X:{},["publishable_key","secret_key"])&&S.push({id:"stripe",label:"Card (Stripe)"}),v((se=i.paypal)!=null?se:{},["client_id","client_secret"])&&S.push({id:"paypal",label:"PayPal"});const A=S,R=Es(((H=A[0])==null?void 0:H.id)||""),Z=A.length>0&&!!o.payment,F=Ie({email:"",billing:{first_name:"",last_name:"",address_line1:"",city:"",postcode:"",country:""},shipping_rule_id:((he=h[0])==null?void 0:he.id)||null}),y=Ie({loading:!1,error:"",success:!1,orderNumber:"",orderUrl:"index.php?option=com_nxpeasycart&view=order"}),I=oe(()=>d.reduce((E,ee)=>E+(ee.total_cents||0),0)),P=oe(()=>{const E=h.find(ee=>String(ee.id)===String(F.shipping_rule_id));return E?E.price_cents:0}),U=oe(()=>I.value+P.value);return{model:F,cartItems:d,shippingRules:h,subtotal:I,selectedShippingCost:P,total:U,submit:async()=>{var Se,Ce;if(y.error="",d.length===0){y.error="Your cart is empty.";return}y.loading=!0;const E=R.value||((Se=A[0])==null?void 0:Se.id)||"",ee={email:F.email,billing:F.billing,shipping_rule_id:F.shipping_rule_id,items:d.map($=>({sku:$.sku,qty:$.qty,product_id:$.product_id,variant_id:$.variant_id,unit_price_cents:$.unit_price_cents,total_cents:$.total_cents,currency:f,title:$.title})),currency:f,totals:{subtotal_cents:I.value,shipping_cents:P.value,total_cents:U.value},gateway:E};try{if(Z&&E){const _e=await fetch(o.payment,{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-Token":l,"X-Requested-With":"XMLHttpRequest"},body:JSON.stringify(ee),credentials:"same-origin"});if(!_e.ok){const te=`Checkout failed (${_e.status})`;throw new Error(te)}const D=await _e.json(),G=(Ce=D==null?void 0:D.checkout)==null?void 0:Ce.url;if(!G)throw new Error("Missing checkout URL from gateway.");window.location.href=G;return}if(!o.checkout)throw new Error("Checkout endpoint unavailable.");const $=await fetch(o.checkout,{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-Token":l,"X-Requested-With":"XMLHttpRequest"},body:JSON.stringify(ee),credentials:"same-origin"});if(!$.ok){const _e=`Checkout failed (${$.status})`;throw new Error(_e)}const V=await $.json(),K=(V==null?void 0:V.order)||{};y.success=!0,y.orderNumber=K.order_no||"",y.orderUrl=`index.php?option=com_nxpeasycart&view=order&no=${encodeURIComponent(y.orderNumber)}`}catch($){y.error=$.message||"Unable to complete checkout right now."}finally{y.loading=!1}},loading:oe(()=>y.loading),error:oe(()=>y.error),success:oe(()=>y.success),orderNumber:oe(()=>y.orderNumber),orderUrl:oe(()=>y.orderUrl),formatMoney:E=>ht(E,f),gateways:A,selectedGateway:R}}}).mount(e)}},Ar=()=>{document.querySelectorAll("[data-nxp-island]").forEach(e=>{const t=e.dataset.nxpIsland;!t||!Pr[t]||Pr[t](e)})};document.readyState==="loading"?document.addEventListener("DOMContentLoaded",Ar):Ar()})();
