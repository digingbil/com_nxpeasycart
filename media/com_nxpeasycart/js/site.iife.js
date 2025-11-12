(function(){"use strict";/**
* @vue/shared v3.5.22
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/function _s(e){const t=Object.create(null);for(const s of e.split(","))t[s]=1;return s=>s in t}const Y={},ft=[],Ie=()=>{},on=()=>!1,qt=e=>e.charCodeAt(0)===111&&e.charCodeAt(1)===110&&(e.charCodeAt(2)>122||e.charCodeAt(2)<97),gs=e=>e.startsWith("onUpdate:"),de=Object.assign,ms=(e,t)=>{const s=e.indexOf(t);s>-1&&e.splice(s,1)},Jr=Object.prototype.hasOwnProperty,B=(e,t)=>Jr.call(e,t),L=Array.isArray,dt=e=>Vt(e)==="[object Map]",ln=e=>Vt(e)==="[object Set]",N=e=>typeof e=="function",ne=e=>typeof e=="string",Qe=e=>typeof e=="symbol",Z=e=>e!==null&&typeof e=="object",cn=e=>(Z(e)||N(e))&&N(e.then)&&N(e.catch),an=Object.prototype.toString,Vt=e=>an.call(e),Yr=e=>Vt(e).slice(8,-1),un=e=>Vt(e)==="[object Object]",bs=e=>ne(e)&&e!=="NaN"&&e[0]!=="-"&&""+parseInt(e,10)===e,St=_s(",key,ref,ref_for,ref_key,onVnodeBeforeMount,onVnodeMounted,onVnodeBeforeUpdate,onVnodeUpdated,onVnodeBeforeUnmount,onVnodeUnmounted"),Bt=e=>{const t=Object.create(null);return s=>t[s]||(t[s]=e(s))},Qr=/-\w/g,Xe=Bt(e=>e.replace(Qr,t=>t.slice(1).toUpperCase())),Xr=/\B([A-Z])/g,it=Bt(e=>e.replace(Xr,"-$1").toLowerCase()),fn=Bt(e=>e.charAt(0).toUpperCase()+e.slice(1)),ys=Bt(e=>e?`on${fn(e)}`:""),ze=(e,t)=>!Object.is(e,t),vs=(e,...t)=>{for(let s=0;s<e.length;s++)e[s](...t)},dn=(e,t,s,n=!1)=>{Object.defineProperty(e,t,{configurable:!0,enumerable:!1,writable:n,value:s})},zr=e=>{const t=parseFloat(e);return isNaN(t)?e:t};let pn;const Kt=()=>pn||(pn=typeof globalThis!="undefined"?globalThis:typeof self!="undefined"?self:typeof window!="undefined"?window:typeof global!="undefined"?global:{});function xs(e){if(L(e)){const t={};for(let s=0;s<e.length;s++){const n=e[s],r=ne(n)?si(n):xs(n);if(r)for(const i in r)t[i]=r[i]}return t}else if(ne(e)||Z(e))return e}const Zr=/;(?![^(]*\))/g,ei=/:([^]+)/,ti=/\/\*[^]*?\*\//g;function si(e){const t={};return e.replace(ti,"").split(Zr).forEach(s=>{if(s){const n=s.split(ei);n.length>1&&(t[n[0].trim()]=n[1].trim())}}),t}function ws(e){let t="";if(ne(e))t=e;else if(L(e))for(let s=0;s<e.length;s++){const n=ws(e[s]);n&&(t+=n+" ")}else if(Z(e))for(const s in e)e[s]&&(t+=s+" ");return t.trim()}const ni=_s("itemscope,allowfullscreen,formnovalidate,ismap,nomodule,novalidate,readonly");function hn(e){return!!e||e===""}const _n=e=>!!(e&&e.__v_isRef===!0),pe=e=>ne(e)?e:e==null?"":L(e)||Z(e)&&(e.toString===an||!N(e.toString))?_n(e)?pe(e.value):JSON.stringify(e,gn,2):String(e),gn=(e,t)=>_n(t)?gn(e,t.value):dt(t)?{[`Map(${t.size})`]:[...t.entries()].reduce((s,[n,r],i)=>(s[Ss(n,i)+" =>"]=r,s),{})}:ln(t)?{[`Set(${t.size})`]:[...t.values()].map(s=>Ss(s))}:Qe(t)?Ss(t):Z(t)&&!L(t)&&!un(t)?String(t):t,Ss=(e,t="")=>{var s;return Qe(e)?`Symbol(${(s=e.description)!=null?s:t})`:e};/**
* @vue/reactivity v3.5.22
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/let be;class ri{constructor(t=!1){this.detached=t,this._active=!0,this._on=0,this.effects=[],this.cleanups=[],this._isPaused=!1,this.parent=be,!t&&be&&(this.index=(be.scopes||(be.scopes=[])).push(this)-1)}get active(){return this._active}pause(){if(this._active){this._isPaused=!0;let t,s;if(this.scopes)for(t=0,s=this.scopes.length;t<s;t++)this.scopes[t].pause();for(t=0,s=this.effects.length;t<s;t++)this.effects[t].pause()}}resume(){if(this._active&&this._isPaused){this._isPaused=!1;let t,s;if(this.scopes)for(t=0,s=this.scopes.length;t<s;t++)this.scopes[t].resume();for(t=0,s=this.effects.length;t<s;t++)this.effects[t].resume()}}run(t){if(this._active){const s=be;try{return be=this,t()}finally{be=s}}}on(){++this._on===1&&(this.prevScope=be,be=this)}off(){this._on>0&&--this._on===0&&(be=this.prevScope,this.prevScope=void 0)}stop(t){if(this._active){this._active=!1;let s,n;for(s=0,n=this.effects.length;s<n;s++)this.effects[s].stop();for(this.effects.length=0,s=0,n=this.cleanups.length;s<n;s++)this.cleanups[s]();if(this.cleanups.length=0,this.scopes){for(s=0,n=this.scopes.length;s<n;s++)this.scopes[s].stop(!0);this.scopes.length=0}if(!this.detached&&this.parent&&!t){const r=this.parent.scopes.pop();r&&r!==this&&(this.parent.scopes[this.index]=r,r.index=this.index)}this.parent=void 0}}}function ii(){return be}let Q;const Cs=new WeakSet;class mn{constructor(t){this.fn=t,this.deps=void 0,this.depsTail=void 0,this.flags=5,this.next=void 0,this.cleanup=void 0,this.scheduler=void 0,be&&be.active&&be.effects.push(this)}pause(){this.flags|=64}resume(){this.flags&64&&(this.flags&=-65,Cs.has(this)&&(Cs.delete(this),this.trigger()))}notify(){this.flags&2&&!(this.flags&32)||this.flags&8||yn(this)}run(){if(!(this.flags&1))return this.fn();this.flags|=2,Cn(this),vn(this);const t=Q,s=Pe;Q=this,Pe=!0;try{return this.fn()}finally{xn(this),Q=t,Pe=s,this.flags&=-3}}stop(){if(this.flags&1){for(let t=this.deps;t;t=t.nextDep)Ps(t);this.deps=this.depsTail=void 0,Cn(this),this.onStop&&this.onStop(),this.flags&=-2}}trigger(){this.flags&64?Cs.add(this):this.scheduler?this.scheduler():this.runIfDirty()}runIfDirty(){ks(this)&&this.run()}get dirty(){return ks(this)}}let bn=0,Ct,At;function yn(e,t=!1){if(e.flags|=8,t){e.next=At,At=e;return}e.next=Ct,Ct=e}function As(){bn++}function Ts(){if(--bn>0)return;if(At){let t=At;for(At=void 0;t;){const s=t.next;t.next=void 0,t.flags&=-9,t=s}}let e;for(;Ct;){let t=Ct;for(Ct=void 0;t;){const s=t.next;if(t.next=void 0,t.flags&=-9,t.flags&1)try{t.trigger()}catch(n){e||(e=n)}t=s}}if(e)throw e}function vn(e){for(let t=e.deps;t;t=t.nextDep)t.version=-1,t.prevActiveLink=t.dep.activeLink,t.dep.activeLink=t}function xn(e){let t,s=e.depsTail,n=s;for(;n;){const r=n.prevDep;n.version===-1?(n===s&&(s=r),Ps(n),oi(n)):t=n,n.dep.activeLink=n.prevActiveLink,n.prevActiveLink=void 0,n=r}e.deps=t,e.depsTail=s}function ks(e){for(let t=e.deps;t;t=t.nextDep)if(t.dep.version!==t.version||t.dep.computed&&(wn(t.dep.computed)||t.dep.version!==t.version))return!0;return!!e._dirty}function wn(e){if(e.flags&4&&!(e.flags&16)||(e.flags&=-17,e.globalVersion===Tt)||(e.globalVersion=Tt,!e.isSSR&&e.flags&128&&(!e.deps&&!e._dirty||!ks(e))))return;e.flags|=2;const t=e.dep,s=Q,n=Pe;Q=e,Pe=!0;try{vn(e);const r=e.fn(e._value);(t.version===0||ze(r,e._value))&&(e.flags|=128,e._value=r,t.version++)}catch(r){throw t.version++,r}finally{Q=s,Pe=n,xn(e),e.flags&=-3}}function Ps(e,t=!1){const{dep:s,prevSub:n,nextSub:r}=e;if(n&&(n.nextSub=r,e.prevSub=void 0),r&&(r.prevSub=n,e.nextSub=void 0),s.subs===e&&(s.subs=n,!n&&s.computed)){s.computed.flags&=-5;for(let i=s.computed.deps;i;i=i.nextDep)Ps(i,!0)}!t&&!--s.sc&&s.map&&s.map.delete(s.key)}function oi(e){const{prevDep:t,nextDep:s}=e;t&&(t.nextDep=s,e.prevDep=void 0),s&&(s.prevDep=t,e.nextDep=void 0)}let Pe=!0;const Sn=[];function Me(){Sn.push(Pe),Pe=!1}function Re(){const e=Sn.pop();Pe=e===void 0?!0:e}function Cn(e){const{cleanup:t}=e;if(e.cleanup=void 0,t){const s=Q;Q=void 0;try{t()}finally{Q=s}}}let Tt=0;class li{constructor(t,s){this.sub=t,this.dep=s,this.version=s.version,this.nextDep=this.prevDep=this.nextSub=this.prevSub=this.prevActiveLink=void 0}}class Es{constructor(t){this.computed=t,this.version=0,this.activeLink=void 0,this.subs=void 0,this.map=void 0,this.key=void 0,this.sc=0,this.__v_skip=!0}track(t){if(!Q||!Pe||Q===this.computed)return;let s=this.activeLink;if(s===void 0||s.sub!==Q)s=this.activeLink=new li(Q,this),Q.deps?(s.prevDep=Q.depsTail,Q.depsTail.nextDep=s,Q.depsTail=s):Q.deps=Q.depsTail=s,An(s);else if(s.version===-1&&(s.version=this.version,s.nextDep)){const n=s.nextDep;n.prevDep=s.prevDep,s.prevDep&&(s.prevDep.nextDep=n),s.prevDep=Q.depsTail,s.nextDep=void 0,Q.depsTail.nextDep=s,Q.depsTail=s,Q.deps===s&&(Q.deps=n)}return s}trigger(t){this.version++,Tt++,this.notify(t)}notify(t){As();try{for(let s=this.subs;s;s=s.prevSub)s.sub.notify()&&s.sub.dep.notify()}finally{Ts()}}}function An(e){if(e.dep.sc++,e.sub.flags&4){const t=e.dep.computed;if(t&&!e.dep.subs){t.flags|=20;for(let n=t.deps;n;n=n.nextDep)An(n)}const s=e.dep.subs;s!==e&&(e.prevSub=s,s&&(s.nextSub=e)),e.dep.subs=e}}const Os=new WeakMap,ot=Symbol(""),Is=Symbol(""),kt=Symbol("");function ue(e,t,s){if(Pe&&Q){let n=Os.get(e);n||Os.set(e,n=new Map);let r=n.get(s);r||(n.set(s,r=new Es),r.map=n,r.key=s),r.track()}}function We(e,t,s,n,r,i){const o=Os.get(e);if(!o){Tt++;return}const l=a=>{a&&a.trigger()};if(As(),t==="clear")o.forEach(l);else{const a=L(e),d=a&&bs(s);if(a&&s==="length"){const f=Number(n);o.forEach((h,v)=>{(v==="length"||v===kt||!Qe(v)&&v>=f)&&l(h)})}else switch((s!==void 0||o.has(void 0))&&l(o.get(s)),d&&l(o.get(kt)),t){case"add":a?d&&l(o.get("length")):(l(o.get(ot)),dt(e)&&l(o.get(Is)));break;case"delete":a||(l(o.get(ot)),dt(e)&&l(o.get(Is)));break;case"set":dt(e)&&l(o.get(ot));break}}Ts()}function pt(e){const t=U(e);return t===e?t:(ue(t,"iterate",kt),Te(e)?t:t.map(ce))}function Wt(e){return ue(e=U(e),"iterate",kt),e}const ci={__proto__:null,[Symbol.iterator](){return Ms(this,Symbol.iterator,ce)},concat(...e){return pt(this).concat(...e.map(t=>L(t)?pt(t):t))},entries(){return Ms(this,"entries",e=>(e[1]=ce(e[1]),e))},every(e,t){return Ge(this,"every",e,t,void 0,arguments)},filter(e,t){return Ge(this,"filter",e,t,s=>s.map(ce),arguments)},find(e,t){return Ge(this,"find",e,t,ce,arguments)},findIndex(e,t){return Ge(this,"findIndex",e,t,void 0,arguments)},findLast(e,t){return Ge(this,"findLast",e,t,ce,arguments)},findLastIndex(e,t){return Ge(this,"findLastIndex",e,t,void 0,arguments)},forEach(e,t){return Ge(this,"forEach",e,t,void 0,arguments)},includes(...e){return Rs(this,"includes",e)},indexOf(...e){return Rs(this,"indexOf",e)},join(e){return pt(this).join(e)},lastIndexOf(...e){return Rs(this,"lastIndexOf",e)},map(e,t){return Ge(this,"map",e,t,void 0,arguments)},pop(){return Pt(this,"pop")},push(...e){return Pt(this,"push",e)},reduce(e,...t){return Tn(this,"reduce",e,t)},reduceRight(e,...t){return Tn(this,"reduceRight",e,t)},shift(){return Pt(this,"shift")},some(e,t){return Ge(this,"some",e,t,void 0,arguments)},splice(...e){return Pt(this,"splice",e)},toReversed(){return pt(this).toReversed()},toSorted(e){return pt(this).toSorted(e)},toSpliced(...e){return pt(this).toSpliced(...e)},unshift(...e){return Pt(this,"unshift",e)},values(){return Ms(this,"values",ce)}};function Ms(e,t,s){const n=Wt(e),r=n[t]();return n!==e&&!Te(e)&&(r._next=r.next,r.next=()=>{const i=r._next();return i.done||(i.value=s(i.value)),i}),r}const ai=Array.prototype;function Ge(e,t,s,n,r,i){const o=Wt(e),l=o!==e&&!Te(e),a=o[t];if(a!==ai[t]){const h=a.apply(e,i);return l?ce(h):h}let d=s;o!==e&&(l?d=function(h,v){return s.call(this,ce(h),v,e)}:s.length>2&&(d=function(h,v){return s.call(this,h,v,e)}));const f=a.call(o,d,n);return l&&r?r(f):f}function Tn(e,t,s,n){const r=Wt(e);let i=s;return r!==e&&(Te(e)?s.length>3&&(i=function(o,l,a){return s.call(this,o,l,a,e)}):i=function(o,l,a){return s.call(this,o,ce(l),a,e)}),r[t](i,...n)}function Rs(e,t,s){const n=U(e);ue(n,"iterate",kt);const r=n[t](...s);return(r===-1||r===!1)&&Ls(s[0])?(s[0]=U(s[0]),n[t](...s)):r}function Pt(e,t,s=[]){Me(),As();const n=U(e)[t].apply(e,s);return Ts(),Re(),n}const ui=_s("__proto__,__v_isRef,__isVue"),kn=new Set(Object.getOwnPropertyNames(Symbol).filter(e=>e!=="arguments"&&e!=="caller").map(e=>Symbol[e]).filter(Qe));function fi(e){Qe(e)||(e=String(e));const t=U(this);return ue(t,"has",e),t.hasOwnProperty(e)}class Pn{constructor(t=!1,s=!1){this._isReadonly=t,this._isShallow=s}get(t,s,n){if(s==="__v_skip")return t.__v_skip;const r=this._isReadonly,i=this._isShallow;if(s==="__v_isReactive")return!r;if(s==="__v_isReadonly")return r;if(s==="__v_isShallow")return i;if(s==="__v_raw")return n===(r?i?Fn:Rn:i?Mn:In).get(t)||Object.getPrototypeOf(t)===Object.getPrototypeOf(n)?t:void 0;const o=L(t);if(!r){let a;if(o&&(a=ci[s]))return a;if(s==="hasOwnProperty")return fi}const l=Reflect.get(t,s,ae(t)?t:n);if((Qe(s)?kn.has(s):ui(s))||(r||ue(t,"get",s),i))return l;if(ae(l)){const a=o&&bs(s)?l:l.value;return r&&Z(a)?$s(a):a}return Z(l)?r?$s(l):Fe(l):l}}class En extends Pn{constructor(t=!1){super(!1,t)}set(t,s,n,r){let i=t[s];if(!this._isShallow){const a=Ze(i);if(!Te(n)&&!Ze(n)&&(i=U(i),n=U(n)),!L(t)&&ae(i)&&!ae(n))return a||(i.value=n),!0}const o=L(t)&&bs(s)?Number(s)<t.length:B(t,s),l=Reflect.set(t,s,n,ae(t)?t:r);return t===U(r)&&(o?ze(n,i)&&We(t,"set",s,n):We(t,"add",s,n)),l}deleteProperty(t,s){const n=B(t,s);t[s];const r=Reflect.deleteProperty(t,s);return r&&n&&We(t,"delete",s,void 0),r}has(t,s){const n=Reflect.has(t,s);return(!Qe(s)||!kn.has(s))&&ue(t,"has",s),n}ownKeys(t){return ue(t,"iterate",L(t)?"length":ot),Reflect.ownKeys(t)}}class On extends Pn{constructor(t=!1){super(!0,t)}set(t,s){return!0}deleteProperty(t,s){return!0}}const di=new En,pi=new On,hi=new En(!0),_i=new On(!0),Fs=e=>e,Gt=e=>Reflect.getPrototypeOf(e);function gi(e,t,s){return function(...n){const r=this.__v_raw,i=U(r),o=dt(i),l=e==="entries"||e===Symbol.iterator&&o,a=e==="keys"&&o,d=r[e](...n),f=s?Fs:t?Xt:ce;return!t&&ue(i,"iterate",a?Is:ot),{next(){const{value:h,done:v}=d.next();return v?{value:h,done:v}:{value:l?[f(h[0]),f(h[1])]:f(h),done:v}},[Symbol.iterator](){return this}}}}function Jt(e){return function(...t){return e==="delete"?!1:e==="clear"?void 0:this}}function mi(e,t){const s={get(r){const i=this.__v_raw,o=U(i),l=U(r);e||(ze(r,l)&&ue(o,"get",r),ue(o,"get",l));const{has:a}=Gt(o),d=t?Fs:e?Xt:ce;if(a.call(o,r))return d(i.get(r));if(a.call(o,l))return d(i.get(l));i!==o&&i.get(r)},get size(){const r=this.__v_raw;return!e&&ue(U(r),"iterate",ot),r.size},has(r){const i=this.__v_raw,o=U(i),l=U(r);return e||(ze(r,l)&&ue(o,"has",r),ue(o,"has",l)),r===l?i.has(r):i.has(r)||i.has(l)},forEach(r,i){const o=this,l=o.__v_raw,a=U(l),d=t?Fs:e?Xt:ce;return!e&&ue(a,"iterate",ot),l.forEach((f,h)=>r.call(i,d(f),d(h),o))}};return de(s,e?{add:Jt("add"),set:Jt("set"),delete:Jt("delete"),clear:Jt("clear")}:{add(r){!t&&!Te(r)&&!Ze(r)&&(r=U(r));const i=U(this);return Gt(i).has.call(i,r)||(i.add(r),We(i,"add",r,r)),this},set(r,i){!t&&!Te(i)&&!Ze(i)&&(i=U(i));const o=U(this),{has:l,get:a}=Gt(o);let d=l.call(o,r);d||(r=U(r),d=l.call(o,r));const f=a.call(o,r);return o.set(r,i),d?ze(i,f)&&We(o,"set",r,i):We(o,"add",r,i),this},delete(r){const i=U(this),{has:o,get:l}=Gt(i);let a=o.call(i,r);a||(r=U(r),a=o.call(i,r)),l&&l.call(i,r);const d=i.delete(r);return a&&We(i,"delete",r,void 0),d},clear(){const r=U(this),i=r.size!==0,o=r.clear();return i&&We(r,"clear",void 0,void 0),o}}),["keys","values","entries",Symbol.iterator].forEach(r=>{s[r]=gi(r,e,t)}),s}function Yt(e,t){const s=mi(e,t);return(n,r,i)=>r==="__v_isReactive"?!e:r==="__v_isReadonly"?e:r==="__v_raw"?n:Reflect.get(B(s,r)&&r in n?s:n,r,i)}const bi={get:Yt(!1,!1)},yi={get:Yt(!1,!0)},vi={get:Yt(!0,!1)},xi={get:Yt(!0,!0)},In=new WeakMap,Mn=new WeakMap,Rn=new WeakMap,Fn=new WeakMap;function wi(e){switch(e){case"Object":case"Array":return 1;case"Map":case"Set":case"WeakMap":case"WeakSet":return 2;default:return 0}}function Si(e){return e.__v_skip||!Object.isExtensible(e)?0:wi(Yr(e))}function Fe(e){return Ze(e)?e:Qt(e,!1,di,bi,In)}function Ci(e){return Qt(e,!1,hi,yi,Mn)}function $s(e){return Qt(e,!0,pi,vi,Rn)}function pc(e){return Qt(e,!0,_i,xi,Fn)}function Qt(e,t,s,n,r){if(!Z(e)||e.__v_raw&&!(t&&e.__v_isReactive))return e;const i=Si(e);if(i===0)return e;const o=r.get(e);if(o)return o;const l=new Proxy(e,i===2?n:s);return r.set(e,l),l}function ht(e){return Ze(e)?ht(e.__v_raw):!!(e&&e.__v_isReactive)}function Ze(e){return!!(e&&e.__v_isReadonly)}function Te(e){return!!(e&&e.__v_isShallow)}function Ls(e){return e?!!e.__v_raw:!1}function U(e){const t=e&&e.__v_raw;return t?U(t):e}function Ai(e){return!B(e,"__v_skip")&&Object.isExtensible(e)&&dn(e,"__v_skip",!0),e}const ce=e=>Z(e)?Fe(e):e,Xt=e=>Z(e)?$s(e):e;function ae(e){return e?e.__v_isRef===!0:!1}function Ns(e){return Ti(e,!1)}function Ti(e,t){return ae(e)?e:new ki(e,t)}class ki{constructor(t,s){this.dep=new Es,this.__v_isRef=!0,this.__v_isShallow=!1,this._rawValue=s?t:U(t),this._value=s?t:ce(t),this.__v_isShallow=s}get value(){return this.dep.track(),this._value}set value(t){const s=this._rawValue,n=this.__v_isShallow||Te(t)||Ze(t);t=n?t:U(t),ze(t,s)&&(this._rawValue=t,this._value=n?t:ce(t),this.dep.trigger())}}function Ds(e){return ae(e)?e.value:e}const Pi={get:(e,t,s)=>t==="__v_raw"?e:Ds(Reflect.get(e,t,s)),set:(e,t,s,n)=>{const r=e[t];return ae(r)&&!ae(s)?(r.value=s,!0):Reflect.set(e,t,s,n)}};function $n(e){return ht(e)?e:new Proxy(e,Pi)}class Ei{constructor(t,s,n){this.fn=t,this.setter=s,this._value=void 0,this.dep=new Es(this),this.__v_isRef=!0,this.deps=void 0,this.depsTail=void 0,this.flags=16,this.globalVersion=Tt-1,this.next=void 0,this.effect=this,this.__v_isReadonly=!s,this.isSSR=n}notify(){if(this.flags|=16,!(this.flags&8)&&Q!==this)return yn(this,!0),!0}get value(){const t=this.dep.track();return wn(this),t&&(t.version=this.dep.version),this._value}set value(t){this.setter&&this.setter(t)}}function Oi(e,t,s=!1){let n,r;return N(e)?n=e:(n=e.get,r=e.set),new Ei(n,r,s)}const zt={},Zt=new WeakMap;let lt;function Ii(e,t=!1,s=lt){if(s){let n=Zt.get(s);n||Zt.set(s,n=[]),n.push(e)}}function Mi(e,t,s=Y){const{immediate:n,deep:r,once:i,scheduler:o,augmentJob:l,call:a}=s,d=T=>r?T:Te(T)||r===!1||r===0?et(T,1):et(T);let f,h,v,x,P=!1,$=!1;if(ae(e)?(h=()=>e.value,P=Te(e)):ht(e)?(h=()=>d(e),P=!0):L(e)?($=!0,P=e.some(T=>ht(T)||Te(T)),h=()=>e.map(T=>{if(ae(T))return T.value;if(ht(T))return d(T);if(N(T))return a?a(T,2):T()})):N(e)?t?h=a?()=>a(e,2):e:h=()=>{if(v){Me();try{v()}finally{Re()}}const T=lt;lt=f;try{return a?a(e,3,[x]):e(x)}finally{lt=T}}:h=Ie,t&&r){const T=h,q=r===!0?1/0:r;h=()=>et(T(),q)}const R=ii(),k=()=>{f.stop(),R&&R.active&&ms(R.effects,f)};if(i&&t){const T=t;t=(...q)=>{T(...q),k()}}let m=$?new Array(e.length).fill(zt):zt;const E=T=>{if(!(!(f.flags&1)||!f.dirty&&!T))if(t){const q=f.run();if(r||P||($?q.some((le,W)=>ze(le,m[W])):ze(q,m))){v&&v();const le=lt;lt=f;try{const W=[q,m===zt?void 0:$&&m[0]===zt?[]:m,x];m=q,a?a(t,3,W):t(...W)}finally{lt=le}}}else f.run()};return l&&l(E),f=new mn(h),f.scheduler=o?()=>o(E,!1):E,x=T=>Ii(T,!1,f),v=f.onStop=()=>{const T=Zt.get(f);if(T){if(a)a(T,4);else for(const q of T)q();Zt.delete(f)}},t?n?E(!0):m=f.run():o?o(E.bind(null,!0),!0):f.run(),k.pause=f.pause.bind(f),k.resume=f.resume.bind(f),k.stop=k,k}function et(e,t=1/0,s){if(t<=0||!Z(e)||e.__v_skip||(s=s||new Map,(s.get(e)||0)>=t))return e;if(s.set(e,t),t--,ae(e))et(e.value,t,s);else if(L(e))for(let n=0;n<e.length;n++)et(e[n],t,s);else if(ln(e)||dt(e))e.forEach(n=>{et(n,t,s)});else if(un(e)){for(const n in e)et(e[n],t,s);for(const n of Object.getOwnPropertySymbols(e))Object.prototype.propertyIsEnumerable.call(e,n)&&et(e[n],t,s)}return e}/**
* @vue/runtime-core v3.5.22
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/const Et=[];let js=!1;function hc(e,...t){if(js)return;js=!0,Me();const s=Et.length?Et[Et.length-1].component:null,n=s&&s.appContext.config.warnHandler,r=Ri();if(n)_t(n,s,11,[e+t.map(i=>{var o,l;return(l=(o=i.toString)==null?void 0:o.call(i))!=null?l:JSON.stringify(i)}).join(""),s&&s.proxy,r.map(({vnode:i})=>`at <${kr(s,i.type)}>`).join(`
`),r]);else{const i=[`[Vue warn]: ${e}`,...t];r.length&&i.push(`
`,...Fi(r)),console.warn(...i)}Re(),js=!1}function Ri(){let e=Et[Et.length-1];if(!e)return[];const t=[];for(;e;){const s=t[0];s&&s.vnode===e?s.recurseCount++:t.push({vnode:e,recurseCount:0});const n=e.component&&e.component.parent;e=n&&n.vnode}return t}function Fi(e){const t=[];return e.forEach((s,n)=>{t.push(...n===0?[]:[`
`],...$i(s))}),t}function $i({vnode:e,recurseCount:t}){const s=t>0?`... (${t} recursive calls)`:"",n=e.component?e.component.parent==null:!1,r=` at <${kr(e.component,e.type,n)}`,i=">"+s;return e.props?[r,...Li(e.props),i]:[r+i]}function Li(e){const t=[],s=Object.keys(e);return s.slice(0,3).forEach(n=>{t.push(...Ln(n,e[n]))}),s.length>3&&t.push(" ..."),t}function Ln(e,t,s){return ne(t)?(t=JSON.stringify(t),s?t:[`${e}=${t}`]):typeof t=="number"||typeof t=="boolean"||t==null?s?t:[`${e}=${t}`]:ae(t)?(t=Ln(e,U(t.value),!0),s?t:[`${e}=Ref<`,t,">"]):N(t)?[`${e}=fn${t.name?`<${t.name}>`:""}`]:(t=U(t),s?t:[`${e}=`,t])}function _t(e,t,s,n){try{return n?e(...n):e()}catch(r){es(r,t,s)}}function $e(e,t,s,n){if(N(e)){const r=_t(e,t,s,n);return r&&cn(r)&&r.catch(i=>{es(i,t,s)}),r}if(L(e)){const r=[];for(let i=0;i<e.length;i++)r.push($e(e[i],t,s,n));return r}}function es(e,t,s,n=!0){const r=t?t.vnode:null,{errorHandler:i,throwUnhandledErrorInProduction:o}=t&&t.appContext.config||Y;if(t){let l=t.parent;const a=t.proxy,d=`https://vuejs.org/error-reference/#runtime-${s}`;for(;l;){const f=l.ec;if(f){for(let h=0;h<f.length;h++)if(f[h](e,a,d)===!1)return}l=l.parent}if(i){Me(),_t(i,null,10,[e,a,d]),Re();return}}Ni(e,s,r,n,o)}function Ni(e,t,s,n=!0,r=!1){if(r)throw e;console.error(e)}const he=[];let Le=-1;const gt=[];let tt=null,mt=0;const Nn=Promise.resolve();let ts=null;function Di(e){const t=ts||Nn;return e?t.then(this?e.bind(this):e):t}function ji(e){let t=Le+1,s=he.length;for(;t<s;){const n=t+s>>>1,r=he[n],i=Ot(r);i<e||i===e&&r.flags&2?t=n+1:s=n}return t}function Hs(e){if(!(e.flags&1)){const t=Ot(e),s=he[he.length-1];!s||!(e.flags&2)&&t>=Ot(s)?he.push(e):he.splice(ji(t),0,e),e.flags|=1,Dn()}}function Dn(){ts||(ts=Nn.then(Un))}function Hi(e){L(e)?gt.push(...e):tt&&e.id===-1?tt.splice(mt+1,0,e):e.flags&1||(gt.push(e),e.flags|=1),Dn()}function jn(e,t,s=Le+1){for(;s<he.length;s++){const n=he[s];if(n&&n.flags&2){if(e&&n.id!==e.uid)continue;he.splice(s,1),s--,n.flags&4&&(n.flags&=-2),n(),n.flags&4||(n.flags&=-2)}}}function Hn(e){if(gt.length){const t=[...new Set(gt)].sort((s,n)=>Ot(s)-Ot(n));if(gt.length=0,tt){tt.push(...t);return}for(tt=t,mt=0;mt<tt.length;mt++){const s=tt[mt];s.flags&4&&(s.flags&=-2),s.flags&8||s(),s.flags&=-2}tt=null,mt=0}}const Ot=e=>e.id==null?e.flags&2?-1:1/0:e.id;function Un(e){try{for(Le=0;Le<he.length;Le++){const t=he[Le];t&&!(t.flags&8)&&(t.flags&4&&(t.flags&=-2),_t(t,t.i,t.i?15:14),t.flags&4||(t.flags&=-2))}}finally{for(;Le<he.length;Le++){const t=he[Le];t&&(t.flags&=-2)}Le=-1,he.length=0,Hn(),ts=null,(he.length||gt.length)&&Un()}}let Ne=null,qn=null;function ss(e){const t=Ne;return Ne=e,qn=e&&e.type.__scopeId||null,t}function Ui(e,t=Ne,s){if(!t||e._n)return e;const n=(...r)=>{n._d&&yr(-1);const i=ss(t);let o;try{o=e(...r)}finally{ss(i),n._d&&yr(1)}return o};return n._n=!0,n._c=!0,n._d=!0,n}function ct(e,t,s,n){const r=e.dirs,i=t&&t.dirs;for(let o=0;o<r.length;o++){const l=r[o];i&&(l.oldValue=i[o].value);let a=l.dir[n];a&&(Me(),$e(a,s,8,[e.el,l,e,t]),Re())}}const qi=Symbol("_vte"),Vi=e=>e.__isTeleport,Bi=Symbol("_leaveCb");function Us(e,t){e.shapeFlag&6&&e.component?(e.transition=t,Us(e.component.subTree,t)):e.shapeFlag&128?(e.ssContent.transition=t.clone(e.ssContent),e.ssFallback.transition=t.clone(e.ssFallback)):e.transition=t}function Vn(e){e.ids=[e.ids[0]+e.ids[2]+++"-",0,0]}const ns=new WeakMap;function It(e,t,s,n,r=!1){if(L(e)){e.forEach((P,$)=>It(P,t&&(L(t)?t[$]:t),s,n,r));return}if(Mt(n)&&!r){n.shapeFlag&512&&n.type.__asyncResolved&&n.component.subTree.component&&It(e,t,s,n.component.subTree);return}const i=n.shapeFlag&4?en(n.component):n.el,o=r?null:i,{i:l,r:a}=e,d=t&&t.r,f=l.refs===Y?l.refs={}:l.refs,h=l.setupState,v=U(h),x=h===Y?on:P=>B(v,P);if(d!=null&&d!==a){if(Bn(t),ne(d))f[d]=null,x(d)&&(h[d]=null);else if(ae(d)){d.value=null;const P=t;P.k&&(f[P.k]=null)}}if(N(a))_t(a,l,12,[o,f]);else{const P=ne(a),$=ae(a);if(P||$){const R=()=>{if(e.f){const k=P?x(a)?h[a]:f[a]:a.value;if(r)L(k)&&ms(k,i);else if(L(k))k.includes(i)||k.push(i);else if(P)f[a]=[i],x(a)&&(h[a]=f[a]);else{const m=[i];a.value=m,e.k&&(f[e.k]=m)}}else P?(f[a]=o,x(a)&&(h[a]=o)):$&&(a.value=o,e.k&&(f[e.k]=o))};if(o){const k=()=>{R(),ns.delete(e)};k.id=-1,ns.set(e,k),we(k,s)}else Bn(e),R()}}}function Bn(e){const t=ns.get(e);t&&(t.flags|=8,ns.delete(e))}Kt().requestIdleCallback,Kt().cancelIdleCallback;const Mt=e=>!!e.type.__asyncLoader,Kn=e=>e.type.__isKeepAlive;function Ki(e,t){Wn(e,"a",t)}function Wi(e,t){Wn(e,"da",t)}function Wn(e,t,s=me){const n=e.__wdc||(e.__wdc=()=>{let r=s;for(;r;){if(r.isDeactivated)return;r=r.parent}return e()});if(rs(t,n,s),s){let r=s.parent;for(;r&&r.parent;)Kn(r.parent.vnode)&&Gi(n,t,s,r),r=r.parent}}function Gi(e,t,s,n){const r=rs(t,e,n,!0);Gn(()=>{ms(n[t],r)},s)}function rs(e,t,s=me,n=!1){if(s){const r=s[e]||(s[e]=[]),i=t.__weh||(t.__weh=(...o)=>{Me();const l=Dt(s),a=$e(t,s,e,o);return l(),Re(),a});return n?r.unshift(i):r.push(i),i}}const Je=e=>(t,s=me)=>{(!jt||e==="sp")&&rs(e,(...n)=>t(...n),s)},Ji=Je("bm"),Yi=Je("m"),Qi=Je("bu"),Xi=Je("u"),zi=Je("bum"),Gn=Je("um"),Zi=Je("sp"),eo=Je("rtg"),to=Je("rtc");function so(e,t=me){rs("ec",e,t)}const no=Symbol.for("v-ndc");function qs(e,t,s,n){let r;const i=s,o=L(e);if(o||ne(e)){const l=o&&ht(e);let a=!1,d=!1;l&&(a=!Te(e),d=Ze(e),e=Wt(e)),r=new Array(e.length);for(let f=0,h=e.length;f<h;f++)r[f]=t(a?d?Xt(ce(e[f])):ce(e[f]):e[f],f,void 0,i)}else if(typeof e=="number"){r=new Array(e);for(let l=0;l<e;l++)r[l]=t(l+1,l,void 0,i)}else if(Z(e))if(e[Symbol.iterator])r=Array.from(e,(l,a)=>t(l,a,void 0,i));else{const l=Object.keys(e);r=new Array(l.length);for(let a=0,d=l.length;a<d;a++){const f=l[a];r[a]=t(e[f],f,a,i)}}else r=[];return r}const Vs=e=>e?Cr(e)?en(e):Vs(e.parent):null,Rt=de(Object.create(null),{$:e=>e,$el:e=>e.vnode.el,$data:e=>e.data,$props:e=>e.props,$attrs:e=>e.attrs,$slots:e=>e.slots,$refs:e=>e.refs,$parent:e=>Vs(e.parent),$root:e=>Vs(e.root),$host:e=>e.ce,$emit:e=>e.emit,$options:e=>Xn(e),$forceUpdate:e=>e.f||(e.f=()=>{Hs(e.update)}),$nextTick:e=>e.n||(e.n=Di.bind(e.proxy)),$watch:e=>To.bind(e)}),Bs=(e,t)=>e!==Y&&!e.__isScriptSetup&&B(e,t),ro={get({_:e},t){if(t==="__v_skip")return!0;const{ctx:s,setupState:n,data:r,props:i,accessCache:o,type:l,appContext:a}=e;let d;if(t[0]!=="$"){const x=o[t];if(x!==void 0)switch(x){case 1:return n[t];case 2:return r[t];case 4:return s[t];case 3:return i[t]}else{if(Bs(n,t))return o[t]=1,n[t];if(r!==Y&&B(r,t))return o[t]=2,r[t];if((d=e.propsOptions[0])&&B(d,t))return o[t]=3,i[t];if(s!==Y&&B(s,t))return o[t]=4,s[t];Ks&&(o[t]=0)}}const f=Rt[t];let h,v;if(f)return t==="$attrs"&&ue(e.attrs,"get",""),f(e);if((h=l.__cssModules)&&(h=h[t]))return h;if(s!==Y&&B(s,t))return o[t]=4,s[t];if(v=a.config.globalProperties,B(v,t))return v[t]},set({_:e},t,s){const{data:n,setupState:r,ctx:i}=e;return Bs(r,t)?(r[t]=s,!0):n!==Y&&B(n,t)?(n[t]=s,!0):B(e.props,t)||t[0]==="$"&&t.slice(1)in e?!1:(i[t]=s,!0)},has({_:{data:e,setupState:t,accessCache:s,ctx:n,appContext:r,propsOptions:i,type:o}},l){let a,d;return!!(s[l]||e!==Y&&l[0]!=="$"&&B(e,l)||Bs(t,l)||(a=i[0])&&B(a,l)||B(n,l)||B(Rt,l)||B(r.config.globalProperties,l)||(d=o.__cssModules)&&d[l])},defineProperty(e,t,s){return s.get!=null?e._.accessCache[t]=0:B(s,"value")&&this.set(e,t,s.value,null),Reflect.defineProperty(e,t,s)}};function Jn(e){return L(e)?e.reduce((t,s)=>(t[s]=null,t),{}):e}let Ks=!0;function io(e){const t=Xn(e),s=e.proxy,n=e.ctx;Ks=!1,t.beforeCreate&&Yn(t.beforeCreate,e,"bc");const{data:r,computed:i,methods:o,watch:l,provide:a,inject:d,created:f,beforeMount:h,mounted:v,beforeUpdate:x,updated:P,activated:$,deactivated:R,beforeDestroy:k,beforeUnmount:m,destroyed:E,unmounted:T,render:q,renderTracked:le,renderTriggered:W,errorCaptured:te,serverPrefetch:oe,expose:ie,inheritAttrs:Ue,components:M,directives:se,filters:Ee}=t;if(d&&oo(d,n,null),o)for(const V in o){const K=o[V];N(K)&&(n[V]=K.bind(s))}if(r){const V=r.call(s,s);Z(V)&&(e.data=Fe(V))}if(Ks=!0,i)for(const V in i){const K=i[V],ye=N(K)?K.bind(s,s):N(K.get)?K.get.bind(s,s):Ie,D=!N(K)&&N(K.set)?K.set.bind(s):Ie,J=X({get:ye,set:D});Object.defineProperty(n,V,{enumerable:!0,configurable:!0,get:()=>J.value,set:ee=>J.value=ee})}if(l)for(const V in l)Qn(l[V],n,s,V);if(a){const V=N(a)?a.call(s):a;Reflect.ownKeys(V).forEach(K=>{po(K,V[K])})}f&&Yn(f,e,"c");function j(V,K){L(K)?K.forEach(ye=>V(ye.bind(s))):K&&V(K.bind(s))}if(j(Ji,h),j(Yi,v),j(Qi,x),j(Xi,P),j(Ki,$),j(Wi,R),j(so,te),j(to,le),j(eo,W),j(zi,m),j(Gn,T),j(Zi,oe),L(ie))if(ie.length){const V=e.exposed||(e.exposed={});ie.forEach(K=>{Object.defineProperty(V,K,{get:()=>s[K],set:ye=>s[K]=ye,enumerable:!0})})}else e.exposed||(e.exposed={});q&&e.render===Ie&&(e.render=q),Ue!=null&&(e.inheritAttrs=Ue),M&&(e.components=M),se&&(e.directives=se),oe&&Vn(e)}function oo(e,t,s=Ie){L(e)&&(e=Ws(e));for(const n in e){const r=e[n];let i;Z(r)?"default"in r?i=os(r.from||n,r.default,!0):i=os(r.from||n):i=os(r),ae(i)?Object.defineProperty(t,n,{enumerable:!0,configurable:!0,get:()=>i.value,set:o=>i.value=o}):t[n]=i}}function Yn(e,t,s){$e(L(e)?e.map(n=>n.bind(t.proxy)):e.bind(t.proxy),t,s)}function Qn(e,t,s,n){let r=n.includes(".")?hr(s,n):()=>s[n];if(ne(e)){const i=t[e];N(i)&&yt(r,i)}else if(N(e))yt(r,e.bind(s));else if(Z(e))if(L(e))e.forEach(i=>Qn(i,t,s,n));else{const i=N(e.handler)?e.handler.bind(s):t[e.handler];N(i)&&yt(r,i,e)}}function Xn(e){const t=e.type,{mixins:s,extends:n}=t,{mixins:r,optionsCache:i,config:{optionMergeStrategies:o}}=e.appContext,l=i.get(t);let a;return l?a=l:!r.length&&!s&&!n?a=t:(a={},r.length&&r.forEach(d=>is(a,d,o,!0)),is(a,t,o)),Z(t)&&i.set(t,a),a}function is(e,t,s,n=!1){const{mixins:r,extends:i}=t;i&&is(e,i,s,!0),r&&r.forEach(o=>is(e,o,s,!0));for(const o in t)if(!(n&&o==="expose")){const l=lo[o]||s&&s[o];e[o]=l?l(e[o],t[o]):t[o]}return e}const lo={data:zn,props:Zn,emits:Zn,methods:Ft,computed:Ft,beforeCreate:_e,created:_e,beforeMount:_e,mounted:_e,beforeUpdate:_e,updated:_e,beforeDestroy:_e,beforeUnmount:_e,destroyed:_e,unmounted:_e,activated:_e,deactivated:_e,errorCaptured:_e,serverPrefetch:_e,components:Ft,directives:Ft,watch:ao,provide:zn,inject:co};function zn(e,t){return t?e?function(){return de(N(e)?e.call(this,this):e,N(t)?t.call(this,this):t)}:t:e}function co(e,t){return Ft(Ws(e),Ws(t))}function Ws(e){if(L(e)){const t={};for(let s=0;s<e.length;s++)t[e[s]]=e[s];return t}return e}function _e(e,t){return e?[...new Set([].concat(e,t))]:t}function Ft(e,t){return e?de(Object.create(null),e,t):t}function Zn(e,t){return e?L(e)&&L(t)?[...new Set([...e,...t])]:de(Object.create(null),Jn(e),Jn(t!=null?t:{})):t}function ao(e,t){if(!e)return t;if(!t)return e;const s=de(Object.create(null),e);for(const n in t)s[n]=_e(e[n],t[n]);return s}function er(){return{app:null,config:{isNativeTag:on,performance:!1,globalProperties:{},optionMergeStrategies:{},errorHandler:void 0,warnHandler:void 0,compilerOptions:{}},mixins:[],components:{},directives:{},provides:Object.create(null),optionsCache:new WeakMap,propsCache:new WeakMap,emitsCache:new WeakMap}}let uo=0;function fo(e,t){return function(n,r=null){N(n)||(n=de({},n)),r!=null&&!Z(r)&&(r=null);const i=er(),o=new WeakSet,l=[];let a=!1;const d=i.app={_uid:uo++,_component:n,_props:r,_container:null,_context:i,_instance:null,version:Zo,get config(){return i.config},set config(f){},use(f,...h){return o.has(f)||(f&&N(f.install)?(o.add(f),f.install(d,...h)):N(f)&&(o.add(f),f(d,...h))),d},mixin(f){return i.mixins.includes(f)||i.mixins.push(f),d},component(f,h){return h?(i.components[f]=h,d):i.components[f]},directive(f,h){return h?(i.directives[f]=h,d):i.directives[f]},mount(f,h,v){if(!a){const x=d._ceVNode||Ce(n,r);return x.appContext=i,v===!0?v="svg":v===!1&&(v=void 0),e(x,f,v),a=!0,d._container=f,f.__vue_app__=d,en(x.component)}},onUnmount(f){l.push(f)},unmount(){a&&($e(l,d._instance,16),e(null,d._container),delete d._container.__vue_app__)},provide(f,h){return i.provides[f]=h,d},runWithContext(f){const h=bt;bt=d;try{return f()}finally{bt=h}}};return d}}let bt=null;function po(e,t){if(me){let s=me.provides;const n=me.parent&&me.parent.provides;n===s&&(s=me.provides=Object.create(n)),s[e]=t}}function os(e,t,s=!1){const n=Bo();if(n||bt){let r=bt?bt._context.provides:n?n.parent==null||n.ce?n.vnode.appContext&&n.vnode.appContext.provides:n.parent.provides:void 0;if(r&&e in r)return r[e];if(arguments.length>1)return s&&N(t)?t.call(n&&n.proxy):t}}const tr={},sr=()=>Object.create(tr),nr=e=>Object.getPrototypeOf(e)===tr;function ho(e,t,s,n=!1){const r={},i=sr();e.propsDefaults=Object.create(null),rr(e,t,r,i);for(const o in e.propsOptions[0])o in r||(r[o]=void 0);s?e.props=n?r:Ci(r):e.type.props?e.props=r:e.props=i,e.attrs=i}function _o(e,t,s,n){const{props:r,attrs:i,vnode:{patchFlag:o}}=e,l=U(r),[a]=e.propsOptions;let d=!1;if((n||o>0)&&!(o&16)){if(o&8){const f=e.vnode.dynamicProps;for(let h=0;h<f.length;h++){let v=f[h];if(ls(e.emitsOptions,v))continue;const x=t[v];if(a)if(B(i,v))x!==i[v]&&(i[v]=x,d=!0);else{const P=Xe(v);r[P]=Gs(a,l,P,x,e,!1)}else x!==i[v]&&(i[v]=x,d=!0)}}}else{rr(e,t,r,i)&&(d=!0);let f;for(const h in l)(!t||!B(t,h)&&((f=it(h))===h||!B(t,f)))&&(a?s&&(s[h]!==void 0||s[f]!==void 0)&&(r[h]=Gs(a,l,h,void 0,e,!0)):delete r[h]);if(i!==l)for(const h in i)(!t||!B(t,h))&&(delete i[h],d=!0)}d&&We(e.attrs,"set","")}function rr(e,t,s,n){const[r,i]=e.propsOptions;let o=!1,l;if(t)for(let a in t){if(St(a))continue;const d=t[a];let f;r&&B(r,f=Xe(a))?!i||!i.includes(f)?s[f]=d:(l||(l={}))[f]=d:ls(e.emitsOptions,a)||(!(a in n)||d!==n[a])&&(n[a]=d,o=!0)}if(i){const a=U(s),d=l||Y;for(let f=0;f<i.length;f++){const h=i[f];s[h]=Gs(r,a,h,d[h],e,!B(d,h))}}return o}function Gs(e,t,s,n,r,i){const o=e[s];if(o!=null){const l=B(o,"default");if(l&&n===void 0){const a=o.default;if(o.type!==Function&&!o.skipFactory&&N(a)){const{propsDefaults:d}=r;if(s in d)n=d[s];else{const f=Dt(r);n=d[s]=a.call(null,t),f()}}else n=a;r.ce&&r.ce._setProp(s,n)}o[0]&&(i&&!l?n=!1:o[1]&&(n===""||n===it(s))&&(n=!0))}return n}const go=new WeakMap;function ir(e,t,s=!1){const n=s?go:t.propsCache,r=n.get(e);if(r)return r;const i=e.props,o={},l=[];let a=!1;if(!N(e)){const f=h=>{a=!0;const[v,x]=ir(h,t,!0);de(o,v),x&&l.push(...x)};!s&&t.mixins.length&&t.mixins.forEach(f),e.extends&&f(e.extends),e.mixins&&e.mixins.forEach(f)}if(!i&&!a)return Z(e)&&n.set(e,ft),ft;if(L(i))for(let f=0;f<i.length;f++){const h=Xe(i[f]);or(h)&&(o[h]=Y)}else if(i)for(const f in i){const h=Xe(f);if(or(h)){const v=i[f],x=o[h]=L(v)||N(v)?{type:v}:de({},v),P=x.type;let $=!1,R=!0;if(L(P))for(let k=0;k<P.length;++k){const m=P[k],E=N(m)&&m.name;if(E==="Boolean"){$=!0;break}else E==="String"&&(R=!1)}else $=N(P)&&P.name==="Boolean";x[0]=$,x[1]=R,($||B(x,"default"))&&l.push(h)}}const d=[o,l];return Z(e)&&n.set(e,d),d}function or(e){return e[0]!=="$"&&!St(e)}const Js=e=>e==="_"||e==="_ctx"||e==="$stable",Ys=e=>L(e)?e.map(De):[De(e)],mo=(e,t,s)=>{if(t._n)return t;const n=Ui((...r)=>Ys(t(...r)),s);return n._c=!1,n},lr=(e,t,s)=>{const n=e._ctx;for(const r in e){if(Js(r))continue;const i=e[r];if(N(i))t[r]=mo(r,i,n);else if(i!=null){const o=Ys(i);t[r]=()=>o}}},cr=(e,t)=>{const s=Ys(t);e.slots.default=()=>s},ar=(e,t,s)=>{for(const n in t)(s||!Js(n))&&(e[n]=t[n])},bo=(e,t,s)=>{const n=e.slots=sr();if(e.vnode.shapeFlag&32){const r=t._;r?(ar(n,t,s),s&&dn(n,"_",r,!0)):lr(t,n)}else t&&cr(e,t)},yo=(e,t,s)=>{const{vnode:n,slots:r}=e;let i=!0,o=Y;if(n.shapeFlag&32){const l=t._;l?s&&l===1?i=!1:ar(r,t,s):(i=!t.$stable,lr(t,r)),o=t}else t&&(cr(e,t),o={default:1});if(i)for(const l in r)!Js(l)&&o[l]==null&&delete r[l]},we=Fo;function vo(e){return xo(e)}function xo(e,t){const s=Kt();s.__VUE__=!0;const{insert:n,remove:r,patchProp:i,createElement:o,createText:l,createComment:a,setText:d,setElementText:f,parentNode:h,nextSibling:v,setScopeId:x=Ie,insertStaticContent:P}=e,$=(c,u,p,b=null,_=null,g=null,C=void 0,S=null,w=!!u.dynamicChildren)=>{if(c===u)return;c&&!Nt(c,u)&&(b=hs(c),ee(c,_,g,!0),c=null),u.patchFlag===-2&&(w=!1,u.dynamicChildren=null);const{type:y,ref:I,shapeFlag:A}=u;switch(y){case cs:R(c,u,p,b);break;case st:k(c,u,p,b);break;case Xs:c==null&&m(u,p,b,C);break;case ke:M(c,u,p,b,_,g,C,S,w);break;default:A&1?q(c,u,p,b,_,g,C,S,w):A&6?se(c,u,p,b,_,g,C,S,w):(A&64||A&128)&&y.process(c,u,p,b,_,g,C,S,w,Ht)}I!=null&&_?It(I,c&&c.ref,g,u||c,!u):I==null&&c&&c.ref!=null&&It(c.ref,null,g,c,!0)},R=(c,u,p,b)=>{if(c==null)n(u.el=l(u.children),p,b);else{const _=u.el=c.el;u.children!==c.children&&d(_,u.children)}},k=(c,u,p,b)=>{c==null?n(u.el=a(u.children||""),p,b):u.el=c.el},m=(c,u,p,b)=>{[c.el,c.anchor]=P(c.children,u,p,b,c.el,c.anchor)},E=({el:c,anchor:u},p,b)=>{let _;for(;c&&c!==u;)_=v(c),n(c,p,b),c=_;n(u,p,b)},T=({el:c,anchor:u})=>{let p;for(;c&&c!==u;)p=v(c),r(c),c=p;r(u)},q=(c,u,p,b,_,g,C,S,w)=>{u.type==="svg"?C="svg":u.type==="math"&&(C="mathml"),c==null?le(u,p,b,_,g,C,S,w):oe(c,u,_,g,C,S,w)},le=(c,u,p,b,_,g,C,S)=>{let w,y;const{props:I,shapeFlag:A,transition:O,dirs:F}=c;if(w=c.el=o(c.type,g,I&&I.is,I),A&8?f(w,c.children):A&16&&te(c.children,w,null,b,_,Qs(c,g),C,S),F&&ct(c,null,b,"created"),W(w,c,c.scopeId,C,b),I){for(const z in I)z!=="value"&&!St(z)&&i(w,z,null,I[z],g,b);"value"in I&&i(w,"value",null,I.value,g),(y=I.onVnodeBeforeMount)&&je(y,b,c)}F&&ct(c,null,b,"beforeMount");const H=wo(_,O);H&&O.beforeEnter(w),n(w,u,p),((y=I&&I.onVnodeMounted)||H||F)&&we(()=>{y&&je(y,b,c),H&&O.enter(w),F&&ct(c,null,b,"mounted")},_)},W=(c,u,p,b,_)=>{if(p&&x(c,p),b)for(let g=0;g<b.length;g++)x(c,b[g]);if(_){let g=_.subTree;if(u===g||br(g.type)&&(g.ssContent===u||g.ssFallback===u)){const C=_.vnode;W(c,C,C.scopeId,C.slotScopeIds,_.parent)}}},te=(c,u,p,b,_,g,C,S,w=0)=>{for(let y=w;y<c.length;y++){const I=c[y]=S?nt(c[y]):De(c[y]);$(null,I,u,p,b,_,g,C,S)}},oe=(c,u,p,b,_,g,C)=>{const S=u.el=c.el;let{patchFlag:w,dynamicChildren:y,dirs:I}=u;w|=c.patchFlag&16;const A=c.props||Y,O=u.props||Y;let F;if(p&&at(p,!1),(F=O.onVnodeBeforeUpdate)&&je(F,p,u,c),I&&ct(u,c,p,"beforeUpdate"),p&&at(p,!0),(A.innerHTML&&O.innerHTML==null||A.textContent&&O.textContent==null)&&f(S,""),y?ie(c.dynamicChildren,y,S,p,b,Qs(u,_),g):C||K(c,u,S,null,p,b,Qs(u,_),g,!1),w>0){if(w&16)Ue(S,A,O,p,_);else if(w&2&&A.class!==O.class&&i(S,"class",null,O.class,_),w&4&&i(S,"style",A.style,O.style,_),w&8){const H=u.dynamicProps;for(let z=0;z<H.length;z++){const G=H[z],ve=A[G],xe=O[G];(xe!==ve||G==="value")&&i(S,G,ve,xe,_,p)}}w&1&&c.children!==u.children&&f(S,u.children)}else!C&&y==null&&Ue(S,A,O,p,_);((F=O.onVnodeUpdated)||I)&&we(()=>{F&&je(F,p,u,c),I&&ct(u,c,p,"updated")},b)},ie=(c,u,p,b,_,g,C)=>{for(let S=0;S<u.length;S++){const w=c[S],y=u[S],I=w.el&&(w.type===ke||!Nt(w,y)||w.shapeFlag&198)?h(w.el):p;$(w,y,I,null,b,_,g,C,!0)}},Ue=(c,u,p,b,_)=>{if(u!==p){if(u!==Y)for(const g in u)!St(g)&&!(g in p)&&i(c,g,u[g],null,_,b);for(const g in p){if(St(g))continue;const C=p[g],S=u[g];C!==S&&g!=="value"&&i(c,g,S,C,_,b)}"value"in p&&i(c,"value",u.value,p.value,_)}},M=(c,u,p,b,_,g,C,S,w)=>{const y=u.el=c?c.el:l(""),I=u.anchor=c?c.anchor:l("");let{patchFlag:A,dynamicChildren:O,slotScopeIds:F}=u;F&&(S=S?S.concat(F):F),c==null?(n(y,p,b),n(I,p,b),te(u.children||[],p,I,_,g,C,S,w)):A>0&&A&64&&O&&c.dynamicChildren?(ie(c.dynamicChildren,O,p,_,g,C,S),(u.key!=null||_&&u===_.subTree)&&ur(c,u,!0)):K(c,u,p,I,_,g,C,S,w)},se=(c,u,p,b,_,g,C,S,w)=>{u.slotScopeIds=S,c==null?u.shapeFlag&512?_.ctx.activate(u,p,b,C,w):Ee(u,p,b,_,g,C,w):Oe(c,u,w)},Ee=(c,u,p,b,_,g,C)=>{const S=c.component=Vo(c,b,_);if(Kn(c)&&(S.ctx.renderer=Ht),Ko(S,!1,C),S.asyncDep){if(_&&_.registerDep(S,j,C),!c.el){const w=S.subTree=Ce(st);k(null,w,u,p),c.placeholder=w.el}}else j(S,c,u,p,_,g,C)},Oe=(c,u,p)=>{const b=u.component=c.component;if(Mo(c,u,p))if(b.asyncDep&&!b.asyncResolved){V(b,u,p);return}else b.next=u,b.update();else u.el=c.el,b.vnode=u},j=(c,u,p,b,_,g,C)=>{const S=()=>{if(c.isMounted){let{next:A,bu:O,u:F,parent:H,vnode:z}=c;{const Be=fr(c);if(Be){A&&(A.el=z.el,V(c,A,C)),Be.asyncDep.then(()=>{c.isUnmounted||S()});return}}let G=A,ve;at(c,!1),A?(A.el=z.el,V(c,A,C)):A=z,O&&vs(O),(ve=A.props&&A.props.onVnodeBeforeUpdate)&&je(ve,H,A,z),at(c,!0);const xe=gr(c),Ve=c.subTree;c.subTree=xe,$(Ve,xe,h(Ve.el),hs(Ve),c,_,g),A.el=xe.el,G===null&&Ro(c,xe.el),F&&we(F,_),(ve=A.props&&A.props.onVnodeUpdated)&&we(()=>je(ve,H,A,z),_)}else{let A;const{el:O,props:F}=u,{bm:H,m:z,parent:G,root:ve,type:xe}=c,Ve=Mt(u);at(c,!1),H&&vs(H),!Ve&&(A=F&&F.onVnodeBeforeMount)&&je(A,G,u),at(c,!0);{ve.ce&&ve.ce._def.shadowRoot!==!1&&ve.ce._injectChildStyle(xe);const Be=c.subTree=gr(c);$(null,Be,p,b,c,_,g),u.el=Be.el}if(z&&we(z,_),!Ve&&(A=F&&F.onVnodeMounted)){const Be=u;we(()=>je(A,G,Be),_)}(u.shapeFlag&256||G&&Mt(G.vnode)&&G.vnode.shapeFlag&256)&&c.a&&we(c.a,_),c.isMounted=!0,u=p=b=null}};c.scope.on();const w=c.effect=new mn(S);c.scope.off();const y=c.update=w.run.bind(w),I=c.job=w.runIfDirty.bind(w);I.i=c,I.id=c.uid,w.scheduler=()=>Hs(I),at(c,!0),y()},V=(c,u,p)=>{u.component=c;const b=c.vnode.props;c.vnode=u,c.next=null,_o(c,u.props,b,p),yo(c,u.children,p),Me(),jn(c),Re()},K=(c,u,p,b,_,g,C,S,w=!1)=>{const y=c&&c.children,I=c?c.shapeFlag:0,A=u.children,{patchFlag:O,shapeFlag:F}=u;if(O>0){if(O&128){D(y,A,p,b,_,g,C,S,w);return}else if(O&256){ye(y,A,p,b,_,g,C,S,w);return}}F&8?(I&16&&rt(y,_,g),A!==y&&f(p,A)):I&16?F&16?D(y,A,p,b,_,g,C,S,w):rt(y,_,g,!0):(I&8&&f(p,""),F&16&&te(A,p,b,_,g,C,S,w))},ye=(c,u,p,b,_,g,C,S,w)=>{c=c||ft,u=u||ft;const y=c.length,I=u.length,A=Math.min(y,I);let O;for(O=0;O<A;O++){const F=u[O]=w?nt(u[O]):De(u[O]);$(c[O],F,p,null,_,g,C,S,w)}y>I?rt(c,_,g,!0,!1,A):te(u,p,b,_,g,C,S,w,A)},D=(c,u,p,b,_,g,C,S,w)=>{let y=0;const I=u.length;let A=c.length-1,O=I-1;for(;y<=A&&y<=O;){const F=c[y],H=u[y]=w?nt(u[y]):De(u[y]);if(Nt(F,H))$(F,H,p,null,_,g,C,S,w);else break;y++}for(;y<=A&&y<=O;){const F=c[A],H=u[O]=w?nt(u[O]):De(u[O]);if(Nt(F,H))$(F,H,p,null,_,g,C,S,w);else break;A--,O--}if(y>A){if(y<=O){const F=O+1,H=F<I?u[F].el:b;for(;y<=O;)$(null,u[y]=w?nt(u[y]):De(u[y]),p,H,_,g,C,S,w),y++}}else if(y>O)for(;y<=A;)ee(c[y],_,g,!0),y++;else{const F=y,H=y,z=new Map;for(y=H;y<=O;y++){const Ae=u[y]=w?nt(u[y]):De(u[y]);Ae.key!=null&&z.set(Ae.key,y)}let G,ve=0;const xe=O-H+1;let Ve=!1,Be=0;const Ut=new Array(xe);for(y=0;y<xe;y++)Ut[y]=0;for(y=F;y<=A;y++){const Ae=c[y];if(ve>=xe){ee(Ae,_,g,!0);continue}let Ke;if(Ae.key!=null)Ke=z.get(Ae.key);else for(G=H;G<=O;G++)if(Ut[G-H]===0&&Nt(Ae,u[G])){Ke=G;break}Ke===void 0?ee(Ae,_,g,!0):(Ut[Ke-H]=y+1,Ke>=Be?Be=Ke:Ve=!0,$(Ae,u[Ke],p,null,_,g,C,S,w),ve++)}const Kr=Ve?So(Ut):ft;for(G=Kr.length-1,y=xe-1;y>=0;y--){const Ae=H+y,Ke=u[Ae],Wr=u[Ae+1],Gr=Ae+1<I?Wr.el||Wr.placeholder:b;Ut[y]===0?$(null,Ke,p,Gr,_,g,C,S,w):Ve&&(G<0||y!==Kr[G]?J(Ke,p,Gr,2):G--)}}},J=(c,u,p,b,_=null)=>{const{el:g,type:C,transition:S,children:w,shapeFlag:y}=c;if(y&6){J(c.component.subTree,u,p,b);return}if(y&128){c.suspense.move(u,p,b);return}if(y&64){C.move(c,u,p,Ht);return}if(C===ke){n(g,u,p);for(let A=0;A<w.length;A++)J(w[A],u,p,b);n(c.anchor,u,p);return}if(C===Xs){E(c,u,p);return}if(b!==2&&y&1&&S)if(b===0)S.beforeEnter(g),n(g,u,p),we(()=>S.enter(g),_);else{const{leave:A,delayLeave:O,afterLeave:F}=S,H=()=>{c.ctx.isUnmounted?r(g):n(g,u,p)},z=()=>{g._isLeaving&&g[Bi](!0),A(g,()=>{H(),F&&F()})};O?O(g,H,z):z()}else n(g,u,p)},ee=(c,u,p,b=!1,_=!1)=>{const{type:g,props:C,ref:S,children:w,dynamicChildren:y,shapeFlag:I,patchFlag:A,dirs:O,cacheIndex:F}=c;if(A===-2&&(_=!1),S!=null&&(Me(),It(S,null,p,c,!0),Re()),F!=null&&(u.renderCache[F]=void 0),I&256){u.ctx.deactivate(c);return}const H=I&1&&O,z=!Mt(c);let G;if(z&&(G=C&&C.onVnodeBeforeUnmount)&&je(G,u,c),I&6)ps(c.component,p,b);else{if(I&128){c.suspense.unmount(p,b);return}H&&ct(c,null,u,"beforeUnmount"),I&64?c.type.remove(c,u,p,Ht,b):y&&!y.hasOnce&&(g!==ke||A>0&&A&64)?rt(y,u,p,!1,!0):(g===ke&&A&384||!_&&I&16)&&rt(w,u,p),b&&qe(c)}(z&&(G=C&&C.onVnodeUnmounted)||H)&&we(()=>{G&&je(G,u,c),H&&ct(c,null,u,"unmounted")},p)},qe=c=>{const{type:u,el:p,anchor:b,transition:_}=c;if(u===ke){ds(p,b);return}if(u===Xs){T(c);return}const g=()=>{r(p),_&&!_.persisted&&_.afterLeave&&_.afterLeave()};if(c.shapeFlag&1&&_&&!_.persisted){const{leave:C,delayLeave:S}=_,w=()=>C(p,g);S?S(c.el,g,w):w()}else g()},ds=(c,u)=>{let p;for(;c!==u;)p=v(c),r(c),c=p;r(u)},ps=(c,u,p)=>{const{bum:b,scope:_,job:g,subTree:C,um:S,m:w,a:y}=c;dr(w),dr(y),b&&vs(b),_.stop(),g&&(g.flags|=8,ee(C,c,u,p)),S&&we(S,u),we(()=>{c.isUnmounted=!0},u)},rt=(c,u,p,b=!1,_=!1,g=0)=>{for(let C=g;C<c.length;C++)ee(c[C],u,p,b,_)},hs=c=>{if(c.shapeFlag&6)return hs(c.component.subTree);if(c.shapeFlag&128)return c.suspense.next();const u=v(c.anchor||c.el),p=u&&u[qi];return p?v(p):u};let rn=!1;const Br=(c,u,p)=>{c==null?u._vnode&&ee(u._vnode,null,null,!0):$(u._vnode||null,c,u,null,null,null,p),u._vnode=c,rn||(rn=!0,jn(),Hn(),rn=!1)},Ht={p:$,um:ee,m:J,r:qe,mt:Ee,mc:te,pc:K,pbc:ie,n:hs,o:e};return{render:Br,hydrate:void 0,createApp:fo(Br)}}function Qs({type:e,props:t},s){return s==="svg"&&e==="foreignObject"||s==="mathml"&&e==="annotation-xml"&&t&&t.encoding&&t.encoding.includes("html")?void 0:s}function at({effect:e,job:t},s){s?(e.flags|=32,t.flags|=4):(e.flags&=-33,t.flags&=-5)}function wo(e,t){return(!e||e&&!e.pendingBranch)&&t&&!t.persisted}function ur(e,t,s=!1){const n=e.children,r=t.children;if(L(n)&&L(r))for(let i=0;i<n.length;i++){const o=n[i];let l=r[i];l.shapeFlag&1&&!l.dynamicChildren&&((l.patchFlag<=0||l.patchFlag===32)&&(l=r[i]=nt(r[i]),l.el=o.el),!s&&l.patchFlag!==-2&&ur(o,l)),l.type===cs&&l.patchFlag!==-1&&(l.el=o.el),l.type===st&&!l.el&&(l.el=o.el)}}function So(e){const t=e.slice(),s=[0];let n,r,i,o,l;const a=e.length;for(n=0;n<a;n++){const d=e[n];if(d!==0){if(r=s[s.length-1],e[r]<d){t[n]=r,s.push(n);continue}for(i=0,o=s.length-1;i<o;)l=i+o>>1,e[s[l]]<d?i=l+1:o=l;d<e[s[i]]&&(i>0&&(t[n]=s[i-1]),s[i]=n)}}for(i=s.length,o=s[i-1];i-- >0;)s[i]=o,o=t[o];return s}function fr(e){const t=e.subTree.component;if(t)return t.asyncDep&&!t.asyncResolved?t:fr(t)}function dr(e){if(e)for(let t=0;t<e.length;t++)e[t].flags|=8}const Co=Symbol.for("v-scx"),Ao=()=>os(Co);function yt(e,t,s){return pr(e,t,s)}function pr(e,t,s=Y){const{immediate:n,deep:r,flush:i,once:o}=s,l=de({},s),a=t&&n||!t&&i!=="post";let d;if(jt){if(i==="sync"){const x=Ao();d=x.__watcherHandles||(x.__watcherHandles=[])}else if(!a){const x=()=>{};return x.stop=Ie,x.resume=Ie,x.pause=Ie,x}}const f=me;l.call=(x,P,$)=>$e(x,f,P,$);let h=!1;i==="post"?l.scheduler=x=>{we(x,f&&f.suspense)}:i!=="sync"&&(h=!0,l.scheduler=(x,P)=>{P?x():Hs(x)}),l.augmentJob=x=>{t&&(x.flags|=4),h&&(x.flags|=2,f&&(x.id=f.uid,x.i=f))};const v=Mi(e,t,l);return jt&&(d?d.push(v):a&&v()),v}function To(e,t,s){const n=this.proxy,r=ne(e)?e.includes(".")?hr(n,e):()=>n[e]:e.bind(n,n);let i;N(t)?i=t:(i=t.handler,s=t);const o=Dt(this),l=pr(r,i.bind(n),s);return o(),l}function hr(e,t){const s=t.split(".");return()=>{let n=e;for(let r=0;r<s.length&&n;r++)n=n[s[r]];return n}}const ko=(e,t)=>t==="modelValue"||t==="model-value"?e.modelModifiers:e[`${t}Modifiers`]||e[`${Xe(t)}Modifiers`]||e[`${it(t)}Modifiers`];function Po(e,t,...s){if(e.isUnmounted)return;const n=e.vnode.props||Y;let r=s;const i=t.startsWith("update:"),o=i&&ko(n,t.slice(7));o&&(o.trim&&(r=s.map(f=>ne(f)?f.trim():f)),o.number&&(r=s.map(zr)));let l,a=n[l=ys(t)]||n[l=ys(Xe(t))];!a&&i&&(a=n[l=ys(it(t))]),a&&$e(a,e,6,r);const d=n[l+"Once"];if(d){if(!e.emitted)e.emitted={};else if(e.emitted[l])return;e.emitted[l]=!0,$e(d,e,6,r)}}const Eo=new WeakMap;function _r(e,t,s=!1){const n=s?Eo:t.emitsCache,r=n.get(e);if(r!==void 0)return r;const i=e.emits;let o={},l=!1;if(!N(e)){const a=d=>{const f=_r(d,t,!0);f&&(l=!0,de(o,f))};!s&&t.mixins.length&&t.mixins.forEach(a),e.extends&&a(e.extends),e.mixins&&e.mixins.forEach(a)}return!i&&!l?(Z(e)&&n.set(e,null),null):(L(i)?i.forEach(a=>o[a]=null):de(o,i),Z(e)&&n.set(e,o),o)}function ls(e,t){return!e||!qt(t)?!1:(t=t.slice(2).replace(/Once$/,""),B(e,t[0].toLowerCase()+t.slice(1))||B(e,it(t))||B(e,t))}function _c(){}function gr(e){const{type:t,vnode:s,proxy:n,withProxy:r,propsOptions:[i],slots:o,attrs:l,emit:a,render:d,renderCache:f,props:h,data:v,setupState:x,ctx:P,inheritAttrs:$}=e,R=ss(e);let k,m;try{if(s.shapeFlag&4){const T=r||n,q=T;k=De(d.call(q,T,f,h,x,v,P)),m=l}else{const T=t;k=De(T.length>1?T(h,{attrs:l,slots:o,emit:a}):T(h,null)),m=t.props?l:Oo(l)}}catch(T){$t.length=0,es(T,e,1),k=Ce(st)}let E=k;if(m&&$!==!1){const T=Object.keys(m),{shapeFlag:q}=E;T.length&&q&7&&(i&&T.some(gs)&&(m=Io(m,i)),E=vt(E,m,!1,!0))}return s.dirs&&(E=vt(E,null,!1,!0),E.dirs=E.dirs?E.dirs.concat(s.dirs):s.dirs),s.transition&&Us(E,s.transition),k=E,ss(R),k}const Oo=e=>{let t;for(const s in e)(s==="class"||s==="style"||qt(s))&&((t||(t={}))[s]=e[s]);return t},Io=(e,t)=>{const s={};for(const n in e)(!gs(n)||!(n.slice(9)in t))&&(s[n]=e[n]);return s};function Mo(e,t,s){const{props:n,children:r,component:i}=e,{props:o,children:l,patchFlag:a}=t,d=i.emitsOptions;if(t.dirs||t.transition)return!0;if(s&&a>=0){if(a&1024)return!0;if(a&16)return n?mr(n,o,d):!!o;if(a&8){const f=t.dynamicProps;for(let h=0;h<f.length;h++){const v=f[h];if(o[v]!==n[v]&&!ls(d,v))return!0}}}else return(r||l)&&(!l||!l.$stable)?!0:n===o?!1:n?o?mr(n,o,d):!0:!!o;return!1}function mr(e,t,s){const n=Object.keys(t);if(n.length!==Object.keys(e).length)return!0;for(let r=0;r<n.length;r++){const i=n[r];if(t[i]!==e[i]&&!ls(s,i))return!0}return!1}function Ro({vnode:e,parent:t},s){for(;t;){const n=t.subTree;if(n.suspense&&n.suspense.activeBranch===e&&(n.el=e.el),n===e)(e=t.vnode).el=s,t=t.parent;else break}}const br=e=>e.__isSuspense;function Fo(e,t){t&&t.pendingBranch?L(e)?t.effects.push(...e):t.effects.push(e):Hi(e)}const ke=Symbol.for("v-fgt"),cs=Symbol.for("v-txt"),st=Symbol.for("v-cmt"),Xs=Symbol.for("v-stc"),$t=[];let Se=null;function fe(e=!1){$t.push(Se=e?null:[])}function $o(){$t.pop(),Se=$t[$t.length-1]||null}let Lt=1;function yr(e,t=!1){Lt+=e,e<0&&Se&&t&&(Se.hasOnce=!0)}function vr(e){return e.dynamicChildren=Lt>0?Se||ft:null,$o(),Lt>0&&Se&&Se.push(e),e}function ge(e,t,s,n,r,i){return vr(re(e,t,s,n,r,i,!0))}function Lo(e,t,s,n,r){return vr(Ce(e,t,s,n,r,!0))}function xr(e){return e?e.__v_isVNode===!0:!1}function Nt(e,t){return e.type===t.type&&e.key===t.key}const wr=({key:e})=>e!=null?e:null,as=({ref:e,ref_key:t,ref_for:s})=>(typeof e=="number"&&(e=""+e),e!=null?ne(e)||ae(e)||N(e)?{i:Ne,r:e,k:t,f:!!s}:e:null);function re(e,t=null,s=null,n=0,r=null,i=e===ke?0:1,o=!1,l=!1){const a={__v_isVNode:!0,__v_skip:!0,type:e,props:t,key:t&&wr(t),ref:t&&as(t),scopeId:qn,slotScopeIds:null,children:s,component:null,suspense:null,ssContent:null,ssFallback:null,dirs:null,transition:null,el:null,anchor:null,target:null,targetStart:null,targetAnchor:null,staticCount:0,shapeFlag:i,patchFlag:n,dynamicProps:r,dynamicChildren:null,appContext:null,ctx:Ne};return l?(zs(a,s),i&128&&e.normalize(a)):s&&(a.shapeFlag|=ne(s)?8:16),Lt>0&&!o&&Se&&(a.patchFlag>0||i&6)&&a.patchFlag!==32&&Se.push(a),a}const Ce=No;function No(e,t=null,s=null,n=0,r=null,i=!1){if((!e||e===no)&&(e=st),xr(e)){const l=vt(e,t,!0);return s&&zs(l,s),Lt>0&&!i&&Se&&(l.shapeFlag&6?Se[Se.indexOf(e)]=l:Se.push(l)),l.patchFlag=-2,l}if(zo(e)&&(e=e.__vccOpts),t){t=Do(t);let{class:l,style:a}=t;l&&!ne(l)&&(t.class=ws(l)),Z(a)&&(Ls(a)&&!L(a)&&(a=de({},a)),t.style=xs(a))}const o=ne(e)?1:br(e)?128:Vi(e)?64:Z(e)?4:N(e)?2:0;return re(e,t,s,n,r,o,i,!0)}function Do(e){return e?Ls(e)||nr(e)?de({},e):e:null}function vt(e,t,s=!1,n=!1){const{props:r,ref:i,patchFlag:o,children:l,transition:a}=e,d=t?Ho(r||{},t):r,f={__v_isVNode:!0,__v_skip:!0,type:e.type,props:d,key:d&&wr(d),ref:t&&t.ref?s&&i?L(i)?i.concat(as(t)):[i,as(t)]:as(t):i,scopeId:e.scopeId,slotScopeIds:e.slotScopeIds,children:l,target:e.target,targetStart:e.targetStart,targetAnchor:e.targetAnchor,staticCount:e.staticCount,shapeFlag:e.shapeFlag,patchFlag:t&&e.type!==ke?o===-1?16:o|16:o,dynamicProps:e.dynamicProps,dynamicChildren:e.dynamicChildren,appContext:e.appContext,dirs:e.dirs,transition:a,component:e.component,suspense:e.suspense,ssContent:e.ssContent&&vt(e.ssContent),ssFallback:e.ssFallback&&vt(e.ssFallback),placeholder:e.placeholder,el:e.el,anchor:e.anchor,ctx:e.ctx,ce:e.ce};return a&&n&&Us(f,a.clone(f)),f}function jo(e=" ",t=0){return Ce(cs,null,e,t)}function ut(e="",t=!1){return t?(fe(),Lo(st,null,e)):Ce(st,null,e)}function De(e){return e==null||typeof e=="boolean"?Ce(st):L(e)?Ce(ke,null,e.slice()):xr(e)?nt(e):Ce(cs,null,String(e))}function nt(e){return e.el===null&&e.patchFlag!==-1||e.memo?e:vt(e)}function zs(e,t){let s=0;const{shapeFlag:n}=e;if(t==null)t=null;else if(L(t))s=16;else if(typeof t=="object")if(n&65){const r=t.default;r&&(r._c&&(r._d=!1),zs(e,r()),r._c&&(r._d=!0));return}else{s=32;const r=t._;!r&&!nr(t)?t._ctx=Ne:r===3&&Ne&&(Ne.slots._===1?t._=1:(t._=2,e.patchFlag|=1024))}else N(t)?(t={default:t,_ctx:Ne},s=32):(t=String(t),n&64?(s=16,t=[jo(t)]):s=8);e.children=t,e.shapeFlag|=s}function Ho(...e){const t={};for(let s=0;s<e.length;s++){const n=e[s];for(const r in n)if(r==="class")t.class!==n.class&&(t.class=ws([t.class,n.class]));else if(r==="style")t.style=xs([t.style,n.style]);else if(qt(r)){const i=t[r],o=n[r];o&&i!==o&&!(L(i)&&i.includes(o))&&(t[r]=i?[].concat(i,o):o)}else r!==""&&(t[r]=n[r])}return t}function je(e,t,s,n=null){$e(e,t,7,[s,n])}const Uo=er();let qo=0;function Vo(e,t,s){const n=e.type,r=(t?t.appContext:e.appContext)||Uo,i={uid:qo++,vnode:e,type:n,parent:t,appContext:r,root:null,next:null,subTree:null,effect:null,update:null,job:null,scope:new ri(!0),render:null,proxy:null,exposed:null,exposeProxy:null,withProxy:null,provides:t?t.provides:Object.create(r.provides),ids:t?t.ids:["",0,0],accessCache:null,renderCache:[],components:null,directives:null,propsOptions:ir(n,r),emitsOptions:_r(n,r),emit:null,emitted:null,propsDefaults:Y,inheritAttrs:n.inheritAttrs,ctx:Y,data:Y,props:Y,attrs:Y,slots:Y,refs:Y,setupState:Y,setupContext:null,suspense:s,suspenseId:s?s.pendingId:0,asyncDep:null,asyncResolved:!1,isMounted:!1,isUnmounted:!1,isDeactivated:!1,bc:null,c:null,bm:null,m:null,bu:null,u:null,um:null,bum:null,da:null,a:null,rtg:null,rtc:null,ec:null,sp:null};return i.ctx={_:i},i.root=t?t.root:i,i.emit=Po.bind(null,i),e.ce&&e.ce(i),i}let me=null;const Bo=()=>me||Ne;let us,Zs;{const e=Kt(),t=(s,n)=>{let r;return(r=e[s])||(r=e[s]=[]),r.push(n),i=>{r.length>1?r.forEach(o=>o(i)):r[0](i)}};us=t("__VUE_INSTANCE_SETTERS__",s=>me=s),Zs=t("__VUE_SSR_SETTERS__",s=>jt=s)}const Dt=e=>{const t=me;return us(e),e.scope.on(),()=>{e.scope.off(),us(t)}},Sr=()=>{me&&me.scope.off(),us(null)};function Cr(e){return e.vnode.shapeFlag&4}let jt=!1;function Ko(e,t=!1,s=!1){t&&Zs(t);const{props:n,children:r}=e.vnode,i=Cr(e);ho(e,n,i,t),bo(e,r,s||t);const o=i?Wo(e,t):void 0;return t&&Zs(!1),o}function Wo(e,t){const s=e.type;e.accessCache=Object.create(null),e.proxy=new Proxy(e.ctx,ro);const{setup:n}=s;if(n){Me();const r=e.setupContext=n.length>1?Jo(e):null,i=Dt(e),o=_t(n,e,0,[e.props,r]),l=cn(o);if(Re(),i(),(l||e.sp)&&!Mt(e)&&Vn(e),l){if(o.then(Sr,Sr),t)return o.then(a=>{Ar(e,a)}).catch(a=>{es(a,e,0)});e.asyncDep=o}else Ar(e,o)}else Tr(e)}function Ar(e,t,s){N(t)?e.type.__ssrInlineRender?e.ssrRender=t:e.render=t:Z(t)&&(e.setupState=$n(t)),Tr(e)}function Tr(e,t,s){const n=e.type;e.render||(e.render=n.render||Ie);{const r=Dt(e);Me();try{io(e)}finally{Re(),r()}}}const Go={get(e,t){return ue(e,"get",""),e[t]}};function Jo(e){const t=s=>{e.exposed=s||{}};return{attrs:new Proxy(e.attrs,Go),slots:e.slots,emit:e.emit,expose:t}}function en(e){return e.exposed?e.exposeProxy||(e.exposeProxy=new Proxy($n(Ai(e.exposed)),{get(t,s){if(s in t)return t[s];if(s in Rt)return Rt[s](e)},has(t,s){return s in t||s in Rt}})):e.proxy}const Yo=/(?:^|[-_])\w/g,Qo=e=>e.replace(Yo,t=>t.toUpperCase()).replace(/[-_]/g,"");function Xo(e,t=!0){return N(e)?e.displayName||e.name:e.name||t&&e.__name}function kr(e,t,s=!1){let n=Xo(t);if(!n&&t.__file){const r=t.__file.match(/([^/\\]+)\.\w+$/);r&&(n=r[1])}if(!n&&e&&e.parent){const r=i=>{for(const o in i)if(i[o]===t)return o};n=r(e.components||e.parent.type.components)||r(e.appContext.components)}return n?Qo(n):s?"App":"Anonymous"}function zo(e){return N(e)&&"__vccOpts"in e}const X=(e,t)=>Oi(e,t,jt),Zo="3.5.22";/**
* @vue/runtime-dom v3.5.22
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/let tn;const Pr=typeof window!="undefined"&&window.trustedTypes;if(Pr)try{tn=Pr.createPolicy("vue",{createHTML:e=>e})}catch(e){}const Er=tn?e=>tn.createHTML(e):e=>e,el="http://www.w3.org/2000/svg",tl="http://www.w3.org/1998/Math/MathML",Ye=typeof document!="undefined"?document:null,Or=Ye&&Ye.createElement("template"),sl={insert:(e,t,s)=>{t.insertBefore(e,s||null)},remove:e=>{const t=e.parentNode;t&&t.removeChild(e)},createElement:(e,t,s,n)=>{const r=t==="svg"?Ye.createElementNS(el,e):t==="mathml"?Ye.createElementNS(tl,e):s?Ye.createElement(e,{is:s}):Ye.createElement(e);return e==="select"&&n&&n.multiple!=null&&r.setAttribute("multiple",n.multiple),r},createText:e=>Ye.createTextNode(e),createComment:e=>Ye.createComment(e),setText:(e,t)=>{e.nodeValue=t},setElementText:(e,t)=>{e.textContent=t},parentNode:e=>e.parentNode,nextSibling:e=>e.nextSibling,querySelector:e=>Ye.querySelector(e),setScopeId(e,t){e.setAttribute(t,"")},insertStaticContent(e,t,s,n,r,i){const o=s?s.previousSibling:t.lastChild;if(r&&(r===i||r.nextSibling))for(;t.insertBefore(r.cloneNode(!0),s),!(r===i||!(r=r.nextSibling)););else{Or.innerHTML=Er(n==="svg"?`<svg>${e}</svg>`:n==="mathml"?`<math>${e}</math>`:e);const l=Or.content;if(n==="svg"||n==="mathml"){const a=l.firstChild;for(;a.firstChild;)l.appendChild(a.firstChild);l.removeChild(a)}t.insertBefore(l,s)}return[o?o.nextSibling:t.firstChild,s?s.previousSibling:t.lastChild]}},nl=Symbol("_vtc");function rl(e,t,s){const n=e[nl];n&&(t=(t?[t,...n]:[...n]).join(" ")),t==null?e.removeAttribute("class"):s?e.setAttribute("class",t):e.className=t}const Ir=Symbol("_vod"),il=Symbol("_vsh"),ol=Symbol(""),ll=/(?:^|;)\s*display\s*:/;function cl(e,t,s){const n=e.style,r=ne(s);let i=!1;if(s&&!r){if(t)if(ne(t))for(const o of t.split(";")){const l=o.slice(0,o.indexOf(":")).trim();s[l]==null&&fs(n,l,"")}else for(const o in t)s[o]==null&&fs(n,o,"");for(const o in s)o==="display"&&(i=!0),fs(n,o,s[o])}else if(r){if(t!==s){const o=n[ol];o&&(s+=";"+o),n.cssText=s,i=ll.test(s)}}else t&&e.removeAttribute("style");Ir in e&&(e[Ir]=i?n.display:"",e[il]&&(n.display="none"))}const Mr=/\s*!important$/;function fs(e,t,s){if(L(s))s.forEach(n=>fs(e,t,n));else if(s==null&&(s=""),t.startsWith("--"))e.setProperty(t,s);else{const n=al(e,t);Mr.test(s)?e.setProperty(it(n),s.replace(Mr,""),"important"):e[n]=s}}const Rr=["Webkit","Moz","ms"],sn={};function al(e,t){const s=sn[t];if(s)return s;let n=Xe(t);if(n!=="filter"&&n in e)return sn[t]=n;n=fn(n);for(let r=0;r<Rr.length;r++){const i=Rr[r]+n;if(i in e)return sn[t]=i}return t}const Fr="http://www.w3.org/1999/xlink";function $r(e,t,s,n,r,i=ni(t)){n&&t.startsWith("xlink:")?s==null?e.removeAttributeNS(Fr,t.slice(6,t.length)):e.setAttributeNS(Fr,t,s):s==null||i&&!hn(s)?e.removeAttribute(t):e.setAttribute(t,i?"":Qe(s)?String(s):s)}function Lr(e,t,s,n,r){if(t==="innerHTML"||t==="textContent"){s!=null&&(e[t]=t==="innerHTML"?Er(s):s);return}const i=e.tagName;if(t==="value"&&i!=="PROGRESS"&&!i.includes("-")){const l=i==="OPTION"?e.getAttribute("value")||"":e.value,a=s==null?e.type==="checkbox"?"on":"":String(s);(l!==a||!("_value"in e))&&(e.value=a),s==null&&e.removeAttribute(t),e._value=s;return}let o=!1;if(s===""||s==null){const l=typeof e[t];l==="boolean"?s=hn(s):s==null&&l==="string"?(s="",o=!0):l==="number"&&(s=0,o=!0)}try{e[t]=s}catch(l){}o&&e.removeAttribute(r||t)}function ul(e,t,s,n){e.addEventListener(t,s,n)}function fl(e,t,s,n){e.removeEventListener(t,s,n)}const Nr=Symbol("_vei");function dl(e,t,s,n,r=null){const i=e[Nr]||(e[Nr]={}),o=i[t];if(n&&o)o.value=n;else{const[l,a]=pl(t);if(n){const d=i[t]=gl(n,r);ul(e,l,d,a)}else o&&(fl(e,l,o,a),i[t]=void 0)}}const Dr=/(?:Once|Passive|Capture)$/;function pl(e){let t;if(Dr.test(e)){t={};let n;for(;n=e.match(Dr);)e=e.slice(0,e.length-n[0].length),t[n[0].toLowerCase()]=!0}return[e[2]===":"?e.slice(3):it(e.slice(2)),t]}let nn=0;const hl=Promise.resolve(),_l=()=>nn||(hl.then(()=>nn=0),nn=Date.now());function gl(e,t){const s=n=>{if(!n._vts)n._vts=Date.now();else if(n._vts<=s.attached)return;$e(ml(n,s.value),t,5,[n])};return s.value=e,s.attached=_l(),s}function ml(e,t){if(L(t)){const s=e.stopImmediatePropagation;return e.stopImmediatePropagation=()=>{s.call(e),e._stopped=!0},t.map(n=>r=>!r._stopped&&n&&n(r))}else return t}const jr=e=>e.charCodeAt(0)===111&&e.charCodeAt(1)===110&&e.charCodeAt(2)>96&&e.charCodeAt(2)<123,bl=(e,t,s,n,r,i)=>{const o=r==="svg";t==="class"?rl(e,n,o):t==="style"?cl(e,s,n):qt(t)?gs(t)||dl(e,t,s,n,i):(t[0]==="."?(t=t.slice(1),!0):t[0]==="^"?(t=t.slice(1),!1):yl(e,t,n,o))?(Lr(e,t,n),!e.tagName.includes("-")&&(t==="value"||t==="checked"||t==="selected")&&$r(e,t,n,o,i,t!=="value")):e._isVueCE&&(/[A-Z]/.test(t)||!ne(n))?Lr(e,Xe(t),n,i,t):(t==="true-value"?e._trueValue=n:t==="false-value"&&(e._falseValue=n),$r(e,t,n,o))};function yl(e,t,s,n){if(n)return!!(t==="innerHTML"||t==="textContent"||t in e&&jr(t)&&N(s));if(t==="spellcheck"||t==="draggable"||t==="translate"||t==="autocorrect"||t==="form"||t==="list"&&e.tagName==="INPUT"||t==="type"&&e.tagName==="TEXTAREA")return!1;if(t==="width"||t==="height"){const r=e.tagName;if(r==="IMG"||r==="VIDEO"||r==="CANVAS"||r==="SOURCE")return!1}return jr(t)&&ne(s)?!1:t in e}const vl=["ctrl","shift","alt","meta"],xl={stop:e=>e.stopPropagation(),prevent:e=>e.preventDefault(),self:e=>e.target!==e.currentTarget,ctrl:e=>!e.ctrlKey,shift:e=>!e.shiftKey,alt:e=>!e.altKey,meta:e=>!e.metaKey,left:e=>"button"in e&&e.button!==0,middle:e=>"button"in e&&e.button!==1,right:e=>"button"in e&&e.button!==2,exact:(e,t)=>vl.some(s=>e[`${s}Key`]&&!t.includes(s))},wl=(e,t)=>{const s=e._withMods||(e._withMods={}),n=t.join(".");return s[n]||(s[n]=(r,...i)=>{for(let o=0;o<t.length;o++){const l=xl[t[o]];if(l&&l(r,t))return}return e(r,...i)})},Sl=de({patchProp:bl},sl);let Hr;function Cl(){return Hr||(Hr=vo(Sl))}const xt=(...e)=>{const t=Cl().createApp(...e),{mount:s}=t;return t.mount=n=>{const r=Tl(n);if(!r)return;const i=t._component;!N(i)&&!i.render&&!i.template&&(i.template=r.innerHTML),r.nodeType===1&&(r.textContent="");const o=s(r,!1,Al(r));return r instanceof Element&&(r.removeAttribute("v-cloak"),r.setAttribute("data-v-app","")),o},t};function Al(e){if(e instanceof SVGElement)return"svg";if(typeof MathMLElement=="function"&&e instanceof MathMLElement)return"mathml"}function Tl(e){return ne(e)?document.querySelector(e):e}const kl={class:"nxp-ec-landing__hero"},Pl={class:"nxp-ec-landing__hero-copy"},El={key:0,class:"nxp-ec-landing__eyebrow"},Ol={class:"nxp-ec-landing__title"},Il={key:1,class:"nxp-ec-landing__subtitle"},Ml={class:"nxp-ec-landing__actions"},Rl=["href"],Fl={class:"sr-only",for:"nxp-ec-landing-search-input"},$l=["value","placeholder"],Ll={type:"submit",class:"nxp-ec-btn nxp-ec-btn--ghost"},Nl={__name:"LandingHero",props:{hero:{type:Object,default:()=>({})},cta:{type:Object,default:()=>({label:"Shop Now",link:"#"})},labels:{type:Object,default:()=>({search_label:"Search the catalogue",search_button:"Search"})},term:{type:String,default:""},searchPlaceholder:{type:String,default:""}},emits:["update:term","submit"],setup(e,{emit:t}){const s=t,n=i=>{s("update:term",i)},r=()=>{s("submit")};return(i,o)=>(fe(),ge("header",kl,[re("div",Pl,[e.hero.eyebrow?(fe(),ge("p",El,pe(e.hero.eyebrow),1)):ut("",!0),re("h1",Ol,pe(e.hero.title),1),e.hero.subtitle?(fe(),ge("p",Il,pe(e.hero.subtitle),1)):ut("",!0),re("div",Ml,[re("a",{class:"nxp-ec-btn nxp-ec-btn--primary",href:e.cta.link},pe(e.cta.label),9,Rl)])]),re("form",{class:"nxp-ec-landing__search",onSubmit:wl(r,["prevent"])},[re("label",Fl,pe(e.labels.search_label),1),re("input",{id:"nxp-ec-landing-search-input",type:"search",value:e.term,onInput:o[0]||(o[0]=l=>n(l.target.value)),placeholder:e.searchPlaceholder},null,40,$l),re("button",Ll,pe(e.labels.search_button),1)],32)]))}},Dl=["aria-label"],jl=["href"],Hl={class:"nxp-ec-landing__category-title"},Ul={__name:"LandingCategories",props:{categories:{type:Array,default:()=>[]},ariaLabel:{type:String,default:""}},setup(e){return(t,s)=>e.categories.length?(fe(),ge("section",{key:0,class:"nxp-ec-landing__categories","aria-label":e.ariaLabel},[(fe(!0),ge(ke,null,qs(e.categories,n=>(fe(),ge("a",{key:n.id||n.slug||n.title,class:"nxp-ec-landing__category",href:n.link},[re("span",Hl,pe(n.title),1)],8,jl))),128))],8,Dl)):ut("",!0)}},ql={class:"nxp-ec-landing__section-header"},Vl={class:"nxp-ec-landing__section-title"},Bl=["href"],Kl={class:"nxp-ec-landing__grid"},Wl={key:0,class:"nxp-ec-landing__card-media"},Gl=["src","alt"],Jl={class:"nxp-ec-landing__card-body"},Yl={class:"nxp-ec-landing__card-title"},Ql=["href"],Xl={key:0,class:"nxp-ec-landing__card-intro"},zl={key:1,class:"nxp-ec-landing__card-price"},Zl=["href"],ec={__name:"LandingSections",props:{sections:{type:Array,default:()=>[]},labels:{type:Object,default:()=>({view_all:"View all",view_product:"View product"})},searchAction:{type:String,default:""}},setup(e){return(t,s)=>(fe(!0),ge(ke,null,qs(e.sections,n=>(fe(),ge("section",{key:n.key,class:"nxp-ec-landing__section"},[re("header",ql,[re("h2",Vl,pe(n.title),1),re("a",{class:"nxp-ec-landing__section-link",href:e.searchAction},pe(e.labels.view_all),9,Bl)]),re("div",Kl,[(fe(!0),ge(ke,null,qs(n.items,r=>(fe(),ge("article",{key:r.id||r.slug||r.title,class:"nxp-ec-landing__card"},[r.images&&r.images.length?(fe(),ge("figure",Wl,[re("img",{src:r.images[0],alt:r.title,loading:"lazy"},null,8,Gl)])):ut("",!0),re("div",Jl,[re("h3",Yl,[re("a",{href:r.link},pe(r.title),9,Ql)]),r.short_desc?(fe(),ge("p",Xl,pe(r.short_desc),1)):ut("",!0),r.price_label?(fe(),ge("p",zl,pe(r.price_label),1)):ut("",!0),re("a",{class:"nxp-ec-btn nxp-ec-btn--ghost",href:r.link},pe(e.labels.view_product),9,Zl)])]))),128))])]))),128))}},tc={key:0,class:"nxp-ec-landing__trust"},sc={class:"nxp-ec-landing__trust-text"},nc={__name:"LandingTrust",props:{trust:{type:Object,default:()=>({text:""})}},setup(e){return(t,s)=>e.trust.text?(fe(),ge("aside",tc,[re("p",sc,pe(e.trust.text),1)])):ut("",!0)}},rc=12;function ic(e,t=rc){return X(()=>oc(Ds(e)).map(n=>({key:n.key||n.title||`section-${n.__index}`,title:n.title||"",items:(n.items||[]).slice(0,t)})))}function oc(e){return Array.isArray(e)?e.filter(t=>t&&typeof t=="object"&&Array.isArray(t.items)&&t.items.length).map((t,s)=>({...t,__index:s})):[]}const lc={class:"nxp-ec-landing__inner"},Ur="index.php?option=com_nxpeasycart&view=category",cc={__name:"LandingApp",props:{hero:{type:Object,default:()=>({})},cta:{type:Object,default:()=>({label:"Shop Best Sellers",link:"index.php?option=com_nxpeasycart&view=category"})},categories:{type:Array,default:()=>[]},sections:{type:Array,default:()=>[]},labels:{type:Object,default:()=>({})},trust:{type:Object,default:()=>({text:""})},searchAction:{type:String,default:"index.php?option=com_nxpeasycart&view=category"},searchPlaceholder:{type:String,default:""}},setup(e,{expose:t}){const s=e,n=Ns(""),r=X(()=>{var R,k,m;return{eyebrow:((R=s.hero)==null?void 0:R.eyebrow)||"",title:((k=s.hero)==null?void 0:k.title)||"Shop",subtitle:((m=s.hero)==null?void 0:m.subtitle)||""}}),i=X(()=>{var R,k;return{label:((R=s.cta)==null?void 0:R.label)||"Shop Best Sellers",link:((k=s.cta)==null?void 0:k.link)||s.searchAction||Ur}}),o=X(()=>{var R,k,m,E,T;return{search_label:((R=s.labels)==null?void 0:R.search_label)||"Search the catalogue",search_button:((k=s.labels)==null?void 0:k.search_button)||"Search",view_all:((m=s.labels)==null?void 0:m.view_all)||"View all",view_product:((E=s.labels)==null?void 0:E.view_product)||"View product",categories_aria:((T=s.labels)==null?void 0:T.categories_aria)||"Browse categories"}}),l=X(()=>{var R;return(R=s.categories)!=null?R:[]}),a=X(()=>{var R;return(R=s.sections)!=null?R:[]}),d=ic(a),f=X(()=>s.trust&&typeof s.trust.text=="string"?s.trust:{text:""}),h=X(()=>s.searchAction||Ur),v=X(()=>s.searchPlaceholder||"Search for shoes, laptops, gifts…"),x=R=>{n.value=R},P=R=>{const k=h.value;try{const m=new URL(k,window.location.origin);R?m.searchParams.set("q",R):m.searchParams.delete("q"),window.location.href=m.toString()}catch(m){if(R){const E=k.includes("?")?"&":"?";window.location.href=`${k}${E}q=${encodeURIComponent(R)}`;return}window.location.href=k}},$=()=>{P(n.value.trim())};return t({submitSearch:$}),(R,k)=>(fe(),ge("div",lc,[Ce(Nl,{hero:r.value,cta:i.value,labels:o.value,term:n.value,"search-placeholder":v.value,"onUpdate:term":x,onSubmit:$},null,8,["hero","cta","labels","term","search-placeholder"]),Ce(Ul,{categories:l.value,"aria-label":o.value.categories_aria},null,8,["categories","aria-label"]),Ce(ec,{sections:Ds(d),labels:o.value,"search-action":h.value},null,8,["sections","labels","search-action"]),Ce(nc,{trust:f.value},null,8,["trust"])]))}};function He(e,t={}){if(!e)return t;try{return JSON.parse(e)}catch(s){return console.warn("[NXP Easy Cart] Failed to parse island payload",s),t}}const ac="index.php?option=com_nxpeasycart&view=category",uc="Search for shoes, laptops, gifts…";function fc(e){var v,x;const t=He(e.dataset.nxpLanding,{}),s=t.hero||{},n=t.search||{},r=t.labels||{},i=t.trust||{},o=Array.isArray(t.sections)?t.sections:[],l=Array.isArray(t.categories)?t.categories:[],a=n.action||ac,d={eyebrow:s.eyebrow||"",title:s.title||"Shop",subtitle:s.subtitle||""},f={label:((v=s==null?void 0:s.cta)==null?void 0:v.label)||"Shop Best Sellers",link:((x=s==null?void 0:s.cta)==null?void 0:x.link)||a},h={search_label:r.search_label||"Search the catalogue",search_button:r.search_button||"Search",view_all:r.view_all||"View all",view_product:r.view_product||"View product",categories_aria:r.categories_aria||"Browse categories"};e.innerHTML="",xt(cc,{hero:d,cta:f,categories:l,sections:o,labels:h,trust:typeof i.text=="string"?i:{text:""},searchAction:a,searchPlaceholder:n.placeholder||uc}).mount(e)}const wt=(e,t)=>{const s=(e||0)/100;try{return new Intl.NumberFormat(void 0,{style:"currency",currency:t||"USD",minimumFractionDigits:2}).format(s)}catch(n){return`${t?`${t} `:""}${s.toFixed(2)}`}},qr={product:e=>{var x,P,$,R,k,m,E,T,q,le,W,te,oe;const t=He(e.dataset.nxpProduct,{}),s=t.product||{},r=(Array.isArray(t.variants)?t.variants:[]).map(ie=>({...ie,id:Number(ie.id||0),stock:ie.stock===null||ie.stock===void 0?null:Number(ie.stock)})).filter(ie=>Number.isFinite(ie.id)&&ie.id>0),i={add_to_cart:((x=t.labels)==null?void 0:x.add_to_cart)||"Add to cart",select_variant:((P=t.labels)==null?void 0:P.select_variant)||"Select a variant",out_of_stock:(($=t.labels)==null?void 0:$.out_of_stock)||"Out of stock",added:((R=t.labels)==null?void 0:R.added)||"Added to cart",view_cart:((k=t.labels)==null?void 0:k.view_cart)||"View cart",qty_label:((m=t.labels)==null?void 0:m.qty_label)||"Quantity",error_generic:((E=t.labels)==null?void 0:E.error_generic)||"We couldn't add this item to your cart. Please try again.",variants_heading:((T=t.labels)==null?void 0:T.variants_heading)||"Variants",variant_sku:((q=t.labels)==null?void 0:q.variant_sku)||"SKU",variant_price:((le=t.labels)==null?void 0:le.variant_price)||"Price",variant_stock:((W=t.labels)==null?void 0:W.variant_stock)||"Stock",variant_options:((te=t.labels)==null?void 0:te.variant_options)||"Options",variant_none:((oe=t.labels)==null?void 0:oe.variant_none)||"—"},o=t.endpoints||{},l=t.links||{},a=t.token||"",d=Array.isArray(s.images)?s.images:[],f=d.length?d[0]:"",h=t.primary_alt||s.title||i.add_to_cart;e.innerHTML="",xt({template:`
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
    `,setup(){const ie=`nxp-ec-variant-${s.id||"0"}`,Ue=`nxp-ec-qty-${s.id||"0"}`,M=Fe({variantId:r.length===1?r[0].id:null,qty:1,loading:!1,success:!1,successMessage:"",error:""}),se=X(()=>r.length?M.variantId?r.find(D=>D.id===M.variantId)||null:r.length===1?r[0]:null:null),Ee=X(()=>{const D=se.value;if(!D||D.stock===null||D.stock===void 0||!Number.isFinite(D.stock))return;const J=Number(D.stock);if(!(!Number.isFinite(J)||J<=0))return J}),Oe=D=>{let J=Number(D);(!Number.isFinite(J)||J<1)&&(J=1);const ee=Ee.value;return Number.isFinite(ee)&&(J=Math.min(J,ee)),J};yt(()=>M.qty,D=>{const J=Oe(D);J!==D&&(M.qty=J)}),yt(()=>M.variantId,()=>{M.error="",M.success=!1,M.successMessage="";const D=Oe(M.qty);D!==M.qty&&(M.qty=D)});const j=X(()=>{var D;return se.value&&se.value.price_label?se.value.price_label:((D=s.price)==null?void 0:D.label)||""}),V=X(()=>{const D=se.value;return!D||D.stock===null||D.stock===void 0?!1:Number(D.stock)<=0}),K=X(()=>!!(M.loading||!r.length||!se.value||V.value));return{product:s,variants:r,labels:i,links:l,primaryImage:f,primaryAlt:h,state:M,add:async()=>{var J;if(M.error="",M.success=!1,M.successMessage="",!o.add){M.error=i.error_generic;return}const D=se.value;if(r.length&&!D){M.error=i.select_variant;return}if(V.value){M.error=i.out_of_stock;return}M.loading=!0;try{const ee=new FormData;a&&ee.append(a,"1"),ee.append("product_id",String(s.id||"")),ee.append("qty",String(Oe(M.qty))),D&&ee.append("variant_id",String(D.id));let qe=null;const ds=await fetch(o.add,{method:"POST",body:ee,headers:{Accept:"application/json"}});try{qe=await ds.json()}catch(rt){}if(!ds.ok||!qe||qe.success===!1){const rt=qe&&qe.message||i.error_generic;throw new Error(rt)}const ps=((J=qe.data)==null?void 0:J.cart)||null;M.success=!0,M.successMessage=qe.message||i.added,ps&&window.dispatchEvent(new CustomEvent("nxp-cart:updated",{detail:ps}))}catch(ee){M.error=ee&&ee.message||i.error_generic}finally{M.loading=!1}},displayPrice:j,isDisabled:K,isOutOfStock:V,maxQty:Ee,variantSelectId:ie,qtyInputId:Ue}}}).mount(e)},category:e=>{const t=He(e.dataset.nxpCategory,{}),s=He(e.dataset.nxpProducts,[]),n=He(e.dataset.nxpCategories,[]),r=He(e.dataset.nxpLabels,{}),i=He(e.dataset.nxpLinks,{}),o=(e.dataset.nxpSearch||"").trim(),l=m=>{if(m==null||m==="")return null;const E=Number.parseInt(m,10);return Number.isFinite(E)?E:null},a={filters:r.filters||"Categories",filter_all:r.filter_all||"All",empty:r.empty||"No products found in this category yet.",view_product:r.view_product||"View product",search_placeholder:r.search_placeholder||"Search products",search_label:r.search_label||r.search_placeholder||"Search products"},d={all:typeof i.all=="string"&&i.all!==""?i.all:"index.php?option=com_nxpeasycart&view=category",search:typeof i.search=="string"&&i.search!==""?i.search:"index.php?option=com_nxpeasycart&view=category"},f=Array.isArray(s)?s:[],h=Array.isArray(n)?n:[],v=t&&typeof t.slug=="string"?t.slug:"",x=`nxp-ec-category-search-${(t==null?void 0:t.id)||"all"}`,P=f.filter(m=>m&&typeof m=="object").map(m=>{const E=m.price&&typeof m.price=="object"?m.price:{},T=l(E.min_cents),q=l(E.max_cents),le=typeof E.currency=="string"&&E.currency!==""?E.currency:"USD";let W=typeof m.price_label=="string"?m.price_label:"";!W&&T!==null&&q!==null&&(T===q?W=wt(T,le):W=`${wt(T,le)} - ${wt(q,le)}`);const te=Array.isArray(m.images)?m.images.filter(oe=>typeof oe=="string"&&oe.trim()!=="").map(oe=>oe.trim()):[];return{...m,title:typeof m.title=="string"?m.title:"",short_desc:typeof m.short_desc=="string"?m.short_desc:"",link:typeof m.link=="string"&&m.link!==""?m.link:"#",images:te,price:{currency:le,min_cents:T,max_cents:q},price_label:W}}),$=h.filter(m=>m&&typeof m=="object").map((m,E)=>({...m,title:typeof m.title=="string"&&m.title!==""?m.title:E===0?a.filter_all:"",slug:typeof m.slug=="string"?m.slug:"",link:typeof m.link=="string"&&m.link!==""?m.link:d.all})),R=m=>{if(!(typeof window=="undefined"||!window.history||typeof window.history.replaceState!="function"))try{const E=new URL(window.location.href);m?E.searchParams.set("q",m):E.searchParams.delete("q"),window.history.replaceState({},"",E.toString())}catch(E){}};e.innerHTML="",xt({template:`
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
    `,setup(){const m=t&&typeof t.title=="string"&&t.title||"Products",E=Ns(o),T=X(()=>{const W=E.value.trim().toLowerCase();return W?P.filter(te=>`${te.title} ${te.short_desc||""}`.toLowerCase().includes(W)):P});return yt(E,(W,te)=>{const oe=W.trim();te!==void 0&&te.trim()===oe||R(oe)},{immediate:!0}),{title:m,search:E,searchId:x,filteredProducts:T,submitSearch:()=>{R(E.value.trim())},labels:a,filters:$,links:d,isActive:W=>(typeof W.slug=="string"?W.slug:"")===v}}}).mount(e)},landing:fc,cart:e=>{const t=He(e.dataset.nxpCart,{items:[],summary:{}});e.innerHTML="",xt({template:`
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
    `,setup(){var d,f,h;const n=Fe(t.items||[]),r=((d=t.summary)==null?void 0:d.currency)||"USD",i=Fe({subtotal_cents:((f=t.summary)==null?void 0:f.subtotal_cents)||0,total_cents:((h=t.summary)==null?void 0:h.total_cents)||0}),o=()=>{const v=n.reduce((x,P)=>x+(P.total_cents||0),0);i.subtotal_cents=v,i.total_cents=v};return{items:n,summary:i,remove:v=>{const x=n.indexOf(v);x>=0&&(n.splice(x,1),o())},updateQty:(v,x)=>{const P=Math.max(1,parseInt(x,10)||1);v.qty=P,v.total_cents=P*(v.unit_price_cents||0),o()},format:v=>wt(v,r)}}}).mount(e)},"cart-summary":e=>{const t=He(e.dataset.nxpCartSummary,{}),s=t.labels||{},n=t.links||{};e.innerHTML="",xt({template:`
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
    `,setup(){const i=Fe({count:Number(t.count||0),total_cents:Number(t.total_cents||0),currency:t.currency||"USD"}),o=X(()=>i.count===1?s.items_single||"1 item":(s.items_plural||"%d items").replace("%d",i.count)),l=X(()=>wt(i.total_cents,i.currency||"USD")),a=d=>{var v,x;if(!d)return;const f=Array.isArray(d.items)?d.items:[];let h=0;f.forEach(P=>{h+=Number(P.qty||0)}),i.count=h,i.total_cents=Number(((v=d.summary)==null?void 0:v.total_cents)||i.total_cents),i.currency=((x=d.summary)==null?void 0:x.currency)||i.currency||"USD"};return window.addEventListener("nxp-cart:updated",d=>{a(d.detail)}),{state:i,labels:s,links:n,countLabel:o,totalLabel:l}}}).mount(e)},checkout:e=>{const t=He(e.dataset.nxpCheckout,{}),s=t.cart||{items:[],summary:{}},n=t.shipping_rules||[];t.tax_rates;const r=t.settings||{},i=t.payments||{},o=t.endpoints||{},l=t.token||"";e.innerHTML="",xt({template:`
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
    `,setup(){var W,te,oe,ie,Ue;const d=Fe((s.items||[]).map(M=>({...M}))),f=((W=s.summary)==null?void 0:W.currency)||r.base_currency||"USD",h=n.map((M,se)=>({...M,price_cents:M.price_cents||0,default:se===0})),v=(M,se=[])=>se.every(Ee=>{var j;const Oe=(j=M[Ee])!=null?j:"";return String(Oe).trim()!==""}),x=[];v((te=i.stripe)!=null?te:{},["publishable_key","secret_key"])&&x.push({id:"stripe",label:"Card (Stripe)"}),v((oe=i.paypal)!=null?oe:{},["client_id","client_secret"])&&x.push({id:"paypal",label:"PayPal"});const P=x,$=Ns(((ie=P[0])==null?void 0:ie.id)||""),R=P.length>0&&!!o.payment,k=Fe({email:"",billing:{first_name:"",last_name:"",address_line1:"",city:"",postcode:"",country:""},shipping_rule_id:((Ue=h[0])==null?void 0:Ue.id)||null}),m=Fe({loading:!1,error:"",success:!1,orderNumber:"",orderUrl:"index.php?option=com_nxpeasycart&view=order"}),E=X(()=>d.reduce((M,se)=>M+(se.total_cents||0),0)),T=X(()=>{const M=h.find(se=>String(se.id)===String(k.shipping_rule_id));return M?M.price_cents:0}),q=X(()=>E.value+T.value);return{model:k,cartItems:d,shippingRules:h,subtotal:E,selectedShippingCost:T,total:q,submit:async()=>{var Ee,Oe;if(m.error="",d.length===0){m.error="Your cart is empty.";return}m.loading=!0;const M=$.value||((Ee=P[0])==null?void 0:Ee.id)||"",se={email:k.email,billing:k.billing,shipping_rule_id:k.shipping_rule_id,items:d.map(j=>({sku:j.sku,qty:j.qty,product_id:j.product_id,variant_id:j.variant_id,unit_price_cents:j.unit_price_cents,total_cents:j.total_cents,currency:f,title:j.title})),currency:f,totals:{subtotal_cents:E.value,shipping_cents:T.value,total_cents:q.value},gateway:M};try{if(R&&M){const ye=await fetch(o.payment,{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-Token":l,"X-Requested-With":"XMLHttpRequest"},body:JSON.stringify(se),credentials:"same-origin"});if(!ye.ok){const ee=`Checkout failed (${ye.status})`;throw new Error(ee)}const D=await ye.json(),J=(Oe=D==null?void 0:D.checkout)==null?void 0:Oe.url;if(!J)throw new Error("Missing checkout URL from gateway.");window.location.href=J;return}if(!o.checkout)throw new Error("Checkout endpoint unavailable.");const j=await fetch(o.checkout,{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-Token":l,"X-Requested-With":"XMLHttpRequest"},body:JSON.stringify(se),credentials:"same-origin"});if(!j.ok){const ye=`Checkout failed (${j.status})`;throw new Error(ye)}const V=await j.json(),K=(V==null?void 0:V.order)||{};m.success=!0,m.orderNumber=K.order_no||"",m.orderUrl=`index.php?option=com_nxpeasycart&view=order&no=${encodeURIComponent(m.orderNumber)}`}catch(j){m.error=j.message||"Unable to complete checkout right now."}finally{m.loading=!1}},loading:X(()=>m.loading),error:X(()=>m.error),success:X(()=>m.success),orderNumber:X(()=>m.orderNumber),orderUrl:X(()=>m.orderUrl),formatMoney:M=>wt(M,f),gateways:P,selectedGateway:$}}}).mount(e)}},Vr=()=>{document.querySelectorAll("[data-nxp-island]").forEach(e=>{const t=e.dataset.nxpIsland;!t||!qr[t]||qr[t](e)})};document.readyState==="loading"?document.addEventListener("DOMContentLoaded",Vr):Vr()})();
