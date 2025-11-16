(function(){"use strict";/**
* @vue/shared v3.5.22
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/function _s(e){const t=Object.create(null);for(const s of e.split(","))t[s]=1;return s=>s in t}const Z={},ft=[],$e=()=>{},on=()=>!1,Vt=e=>e.charCodeAt(0)===111&&e.charCodeAt(1)===110&&(e.charCodeAt(2)>122||e.charCodeAt(2)<97),gs=e=>e.startsWith("onUpdate:"),me=Object.assign,ms=(e,t)=>{const s=e.indexOf(t);s>-1&&e.splice(s,1)},Jr=Object.prototype.hasOwnProperty,W=(e,t)=>Jr.call(e,t),D=Array.isArray,dt=e=>Bt(e)==="[object Map]",ln=e=>Bt(e)==="[object Set]",N=e=>typeof e=="function",oe=e=>typeof e=="string",Qe=e=>typeof e=="symbol",re=e=>e!==null&&typeof e=="object",cn=e=>(re(e)||N(e))&&N(e.then)&&N(e.catch),an=Object.prototype.toString,Bt=e=>an.call(e),Yr=e=>Bt(e).slice(8,-1),un=e=>Bt(e)==="[object Object]",bs=e=>oe(e)&&e!=="NaN"&&e[0]!=="-"&&""+parseInt(e,10)===e,St=_s(",key,ref,ref_for,ref_key,onVnodeBeforeMount,onVnodeMounted,onVnodeBeforeUpdate,onVnodeUpdated,onVnodeBeforeUnmount,onVnodeUnmounted"),Kt=e=>{const t=Object.create(null);return s=>t[s]||(t[s]=e(s))},Qr=/-\w/g,Xe=Kt(e=>e.replace(Qr,t=>t.slice(1).toUpperCase())),Xr=/\B([A-Z])/g,ot=Kt(e=>e.replace(Xr,"-$1").toLowerCase()),fn=Kt(e=>e.charAt(0).toUpperCase()+e.slice(1)),ys=Kt(e=>e?`on${fn(e)}`:""),ze=(e,t)=>!Object.is(e,t),vs=(e,...t)=>{for(let s=0;s<e.length;s++)e[s](...t)},dn=(e,t,s,n=!1)=>{Object.defineProperty(e,t,{configurable:!0,enumerable:!1,writable:n,value:s})},zr=e=>{const t=parseFloat(e);return isNaN(t)?e:t};let pn;const Wt=()=>pn||(pn=typeof globalThis!="undefined"?globalThis:typeof self!="undefined"?self:typeof window!="undefined"?window:typeof global!="undefined"?global:{});function xs(e){if(D(e)){const t={};for(let s=0;s<e.length;s++){const n=e[s],r=oe(n)?si(n):xs(n);if(r)for(const i in r)t[i]=r[i]}return t}else if(oe(e)||re(e))return e}const Zr=/;(?![^(]*\))/g,ei=/:([^]+)/,ti=/\/\*[^]*?\*\//g;function si(e){const t={};return e.replace(ti,"").split(Zr).forEach(s=>{if(s){const n=s.split(ei);n.length>1&&(t[n[0].trim()]=n[1].trim())}}),t}function ws(e){let t="";if(oe(e))t=e;else if(D(e))for(let s=0;s<e.length;s++){const n=ws(e[s]);n&&(t+=n+" ")}else if(re(e))for(const s in e)e[s]&&(t+=s+" ");return t.trim()}const ni=_s("itemscope,allowfullscreen,formnovalidate,ismap,nomodule,novalidate,readonly");function hn(e){return!!e||e===""}const _n=e=>!!(e&&e.__v_isRef===!0),he=e=>oe(e)?e:e==null?"":D(e)||re(e)&&(e.toString===an||!N(e.toString))?_n(e)?he(e.value):JSON.stringify(e,gn,2):String(e),gn=(e,t)=>_n(t)?gn(e,t.value):dt(t)?{[`Map(${t.size})`]:[...t.entries()].reduce((s,[n,r],i)=>(s[Ss(n,i)+" =>"]=r,s),{})}:ln(t)?{[`Set(${t.size})`]:[...t.values()].map(s=>Ss(s))}:Qe(t)?Ss(t):re(t)&&!D(t)&&!un(t)?String(t):t,Ss=(e,t="")=>{var s;return Qe(e)?`Symbol(${(s=e.description)!=null?s:t})`:e};/**
* @vue/reactivity v3.5.22
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/let xe;class ri{constructor(t=!1){this.detached=t,this._active=!0,this._on=0,this.effects=[],this.cleanups=[],this._isPaused=!1,this.parent=xe,!t&&xe&&(this.index=(xe.scopes||(xe.scopes=[])).push(this)-1)}get active(){return this._active}pause(){if(this._active){this._isPaused=!0;let t,s;if(this.scopes)for(t=0,s=this.scopes.length;t<s;t++)this.scopes[t].pause();for(t=0,s=this.effects.length;t<s;t++)this.effects[t].pause()}}resume(){if(this._active&&this._isPaused){this._isPaused=!1;let t,s;if(this.scopes)for(t=0,s=this.scopes.length;t<s;t++)this.scopes[t].resume();for(t=0,s=this.effects.length;t<s;t++)this.effects[t].resume()}}run(t){if(this._active){const s=xe;try{return xe=this,t()}finally{xe=s}}}on(){++this._on===1&&(this.prevScope=xe,xe=this)}off(){this._on>0&&--this._on===0&&(xe=this.prevScope,this.prevScope=void 0)}stop(t){if(this._active){this._active=!1;let s,n;for(s=0,n=this.effects.length;s<n;s++)this.effects[s].stop();for(this.effects.length=0,s=0,n=this.cleanups.length;s<n;s++)this.cleanups[s]();if(this.cleanups.length=0,this.scopes){for(s=0,n=this.scopes.length;s<n;s++)this.scopes[s].stop(!0);this.scopes.length=0}if(!this.detached&&this.parent&&!t){const r=this.parent.scopes.pop();r&&r!==this&&(this.parent.scopes[this.index]=r,r.index=this.index)}this.parent=void 0}}}function ii(){return xe}let ee;const ks=new WeakSet;class mn{constructor(t){this.fn=t,this.deps=void 0,this.depsTail=void 0,this.flags=5,this.next=void 0,this.cleanup=void 0,this.scheduler=void 0,xe&&xe.active&&xe.effects.push(this)}pause(){this.flags|=64}resume(){this.flags&64&&(this.flags&=-65,ks.has(this)&&(ks.delete(this),this.trigger()))}notify(){this.flags&2&&!(this.flags&32)||this.flags&8||yn(this)}run(){if(!(this.flags&1))return this.fn();this.flags|=2,kn(this),vn(this);const t=ee,s=Re;ee=this,Re=!0;try{return this.fn()}finally{xn(this),ee=t,Re=s,this.flags&=-3}}stop(){if(this.flags&1){for(let t=this.deps;t;t=t.nextDep)Es(t);this.deps=this.depsTail=void 0,kn(this),this.onStop&&this.onStop(),this.flags&=-2}}trigger(){this.flags&64?ks.add(this):this.scheduler?this.scheduler():this.runIfDirty()}runIfDirty(){Ts(this)&&this.run()}get dirty(){return Ts(this)}}let bn=0,kt,Ct;function yn(e,t=!1){if(e.flags|=8,t){e.next=Ct,Ct=e;return}e.next=kt,kt=e}function Cs(){bn++}function As(){if(--bn>0)return;if(Ct){let t=Ct;for(Ct=void 0;t;){const s=t.next;t.next=void 0,t.flags&=-9,t=s}}let e;for(;kt;){let t=kt;for(kt=void 0;t;){const s=t.next;if(t.next=void 0,t.flags&=-9,t.flags&1)try{t.trigger()}catch(n){e||(e=n)}t=s}}if(e)throw e}function vn(e){for(let t=e.deps;t;t=t.nextDep)t.version=-1,t.prevActiveLink=t.dep.activeLink,t.dep.activeLink=t}function xn(e){let t,s=e.depsTail,n=s;for(;n;){const r=n.prevDep;n.version===-1?(n===s&&(s=r),Es(n),oi(n)):t=n,n.dep.activeLink=n.prevActiveLink,n.prevActiveLink=void 0,n=r}e.deps=t,e.depsTail=s}function Ts(e){for(let t=e.deps;t;t=t.nextDep)if(t.dep.version!==t.version||t.dep.computed&&(wn(t.dep.computed)||t.dep.version!==t.version))return!0;return!!e._dirty}function wn(e){if(e.flags&4&&!(e.flags&16)||(e.flags&=-17,e.globalVersion===At)||(e.globalVersion=At,!e.isSSR&&e.flags&128&&(!e.deps&&!e._dirty||!Ts(e))))return;e.flags|=2;const t=e.dep,s=ee,n=Re;ee=e,Re=!0;try{vn(e);const r=e.fn(e._value);(t.version===0||ze(r,e._value))&&(e.flags|=128,e._value=r,t.version++)}catch(r){throw t.version++,r}finally{ee=s,Re=n,xn(e),e.flags&=-3}}function Es(e,t=!1){const{dep:s,prevSub:n,nextSub:r}=e;if(n&&(n.nextSub=r,e.prevSub=void 0),r&&(r.prevSub=n,e.nextSub=void 0),s.subs===e&&(s.subs=n,!n&&s.computed)){s.computed.flags&=-5;for(let i=s.computed.deps;i;i=i.nextDep)Es(i,!0)}!t&&!--s.sc&&s.map&&s.map.delete(s.key)}function oi(e){const{prevDep:t,nextDep:s}=e;t&&(t.nextDep=s,e.prevDep=void 0),s&&(s.prevDep=t,e.nextDep=void 0)}let Re=!0;const Sn=[];function Le(){Sn.push(Re),Re=!1}function De(){const e=Sn.pop();Re=e===void 0?!0:e}function kn(e){const{cleanup:t}=e;if(e.cleanup=void 0,t){const s=ee;ee=void 0;try{t()}finally{ee=s}}}let At=0;class li{constructor(t,s){this.sub=t,this.dep=s,this.version=s.version,this.nextDep=this.prevDep=this.nextSub=this.prevSub=this.prevActiveLink=void 0}}class Ps{constructor(t){this.computed=t,this.version=0,this.activeLink=void 0,this.subs=void 0,this.map=void 0,this.key=void 0,this.sc=0,this.__v_skip=!0}track(t){if(!ee||!Re||ee===this.computed)return;let s=this.activeLink;if(s===void 0||s.sub!==ee)s=this.activeLink=new li(ee,this),ee.deps?(s.prevDep=ee.depsTail,ee.depsTail.nextDep=s,ee.depsTail=s):ee.deps=ee.depsTail=s,Cn(s);else if(s.version===-1&&(s.version=this.version,s.nextDep)){const n=s.nextDep;n.prevDep=s.prevDep,s.prevDep&&(s.prevDep.nextDep=n),s.prevDep=ee.depsTail,s.nextDep=void 0,ee.depsTail.nextDep=s,ee.depsTail=s,ee.deps===s&&(ee.deps=n)}return s}trigger(t){this.version++,At++,this.notify(t)}notify(t){Cs();try{for(let s=this.subs;s;s=s.prevSub)s.sub.notify()&&s.sub.dep.notify()}finally{As()}}}function Cn(e){if(e.dep.sc++,e.sub.flags&4){const t=e.dep.computed;if(t&&!e.dep.subs){t.flags|=20;for(let n=t.deps;n;n=n.nextDep)Cn(n)}const s=e.dep.subs;s!==e&&(e.prevSub=s,s&&(s.nextSub=e)),e.dep.subs=e}}const Os=new WeakMap,lt=Symbol(""),Is=Symbol(""),Tt=Symbol("");function _e(e,t,s){if(Re&&ee){let n=Os.get(e);n||Os.set(e,n=new Map);let r=n.get(s);r||(n.set(s,r=new Ps),r.map=n,r.key=s),r.track()}}function We(e,t,s,n,r,i){const o=Os.get(e);if(!o){At++;return}const l=a=>{a&&a.trigger()};if(Cs(),t==="clear")o.forEach(l);else{const a=D(e),d=a&&bs(s);if(a&&s==="length"){const u=Number(n);o.forEach((p,m)=>{(m==="length"||m===Tt||!Qe(m)&&m>=u)&&l(p)})}else switch((s!==void 0||o.has(void 0))&&l(o.get(s)),d&&l(o.get(Tt)),t){case"add":a?d&&l(o.get("length")):(l(o.get(lt)),dt(e)&&l(o.get(Is)));break;case"delete":a||(l(o.get(lt)),dt(e)&&l(o.get(Is)));break;case"set":dt(e)&&l(o.get(lt));break}}As()}function pt(e){const t=K(e);return t===e?t:(_e(t,"iterate",Tt),Oe(e)?t:t.map(ae))}function Gt(e){return _e(e=K(e),"iterate",Tt),e}const ci={__proto__:null,[Symbol.iterator](){return Ms(this,Symbol.iterator,ae)},concat(...e){return pt(this).concat(...e.map(t=>D(t)?pt(t):t))},entries(){return Ms(this,"entries",e=>(e[1]=ae(e[1]),e))},every(e,t){return Ge(this,"every",e,t,void 0,arguments)},filter(e,t){return Ge(this,"filter",e,t,s=>s.map(ae),arguments)},find(e,t){return Ge(this,"find",e,t,ae,arguments)},findIndex(e,t){return Ge(this,"findIndex",e,t,void 0,arguments)},findLast(e,t){return Ge(this,"findLast",e,t,ae,arguments)},findLastIndex(e,t){return Ge(this,"findLastIndex",e,t,void 0,arguments)},forEach(e,t){return Ge(this,"forEach",e,t,void 0,arguments)},includes(...e){return Rs(this,"includes",e)},indexOf(...e){return Rs(this,"indexOf",e)},join(e){return pt(this).join(e)},lastIndexOf(...e){return Rs(this,"lastIndexOf",e)},map(e,t){return Ge(this,"map",e,t,void 0,arguments)},pop(){return Et(this,"pop")},push(...e){return Et(this,"push",e)},reduce(e,...t){return An(this,"reduce",e,t)},reduceRight(e,...t){return An(this,"reduceRight",e,t)},shift(){return Et(this,"shift")},some(e,t){return Ge(this,"some",e,t,void 0,arguments)},splice(...e){return Et(this,"splice",e)},toReversed(){return pt(this).toReversed()},toSorted(e){return pt(this).toSorted(e)},toSpliced(...e){return pt(this).toSpliced(...e)},unshift(...e){return Et(this,"unshift",e)},values(){return Ms(this,"values",ae)}};function Ms(e,t,s){const n=Gt(e),r=n[t]();return n!==e&&!Oe(e)&&(r._next=r.next,r.next=()=>{const i=r._next();return i.done||(i.value=s(i.value)),i}),r}const ai=Array.prototype;function Ge(e,t,s,n,r,i){const o=Gt(e),l=o!==e&&!Oe(e),a=o[t];if(a!==ai[t]){const p=a.apply(e,i);return l?ae(p):p}let d=s;o!==e&&(l?d=function(p,m){return s.call(this,ae(p),m,e)}:s.length>2&&(d=function(p,m){return s.call(this,p,m,e)}));const u=a.call(o,d,n);return l&&r?r(u):u}function An(e,t,s,n){const r=Gt(e);let i=s;return r!==e&&(Oe(e)?s.length>3&&(i=function(o,l,a){return s.call(this,o,l,a,e)}):i=function(o,l,a){return s.call(this,o,ae(l),a,e)}),r[t](i,...n)}function Rs(e,t,s){const n=K(e);_e(n,"iterate",Tt);const r=n[t](...s);return(r===-1||r===!1)&&Ls(s[0])?(s[0]=K(s[0]),n[t](...s)):r}function Et(e,t,s=[]){Le(),Cs();const n=K(e)[t].apply(e,s);return As(),De(),n}const ui=_s("__proto__,__v_isRef,__isVue"),Tn=new Set(Object.getOwnPropertyNames(Symbol).filter(e=>e!=="arguments"&&e!=="caller").map(e=>Symbol[e]).filter(Qe));function fi(e){Qe(e)||(e=String(e));const t=K(this);return _e(t,"has",e),t.hasOwnProperty(e)}class En{constructor(t=!1,s=!1){this._isReadonly=t,this._isShallow=s}get(t,s,n){if(s==="__v_skip")return t.__v_skip;const r=this._isReadonly,i=this._isShallow;if(s==="__v_isReactive")return!r;if(s==="__v_isReadonly")return r;if(s==="__v_isShallow")return i;if(s==="__v_raw")return n===(r?i?Fn:Rn:i?Mn:In).get(t)||Object.getPrototypeOf(t)===Object.getPrototypeOf(n)?t:void 0;const o=D(t);if(!r){let a;if(o&&(a=ci[s]))return a;if(s==="hasOwnProperty")return fi}const l=Reflect.get(t,s,ue(t)?t:n);if((Qe(s)?Tn.has(s):ui(s))||(r||_e(t,"get",s),i))return l;if(ue(l)){const a=o&&bs(s)?l:l.value;return r&&re(a)?$s(a):a}return re(l)?r?$s(l):Pe(l):l}}class Pn extends En{constructor(t=!1){super(!1,t)}set(t,s,n,r){let i=t[s];if(!this._isShallow){const a=Ze(i);if(!Oe(n)&&!Ze(n)&&(i=K(i),n=K(n)),!D(t)&&ue(i)&&!ue(n))return a||(i.value=n),!0}const o=D(t)&&bs(s)?Number(s)<t.length:W(t,s),l=Reflect.set(t,s,n,ue(t)?t:r);return t===K(r)&&(o?ze(n,i)&&We(t,"set",s,n):We(t,"add",s,n)),l}deleteProperty(t,s){const n=W(t,s);t[s];const r=Reflect.deleteProperty(t,s);return r&&n&&We(t,"delete",s,void 0),r}has(t,s){const n=Reflect.has(t,s);return(!Qe(s)||!Tn.has(s))&&_e(t,"has",s),n}ownKeys(t){return _e(t,"iterate",D(t)?"length":lt),Reflect.ownKeys(t)}}class On extends En{constructor(t=!1){super(!0,t)}set(t,s){return!0}deleteProperty(t,s){return!0}}const di=new Pn,pi=new On,hi=new Pn(!0),_i=new On(!0),Fs=e=>e,Jt=e=>Reflect.getPrototypeOf(e);function gi(e,t,s){return function(...n){const r=this.__v_raw,i=K(r),o=dt(i),l=e==="entries"||e===Symbol.iterator&&o,a=e==="keys"&&o,d=r[e](...n),u=s?Fs:t?zt:ae;return!t&&_e(i,"iterate",a?Is:lt),{next(){const{value:p,done:m}=d.next();return m?{value:p,done:m}:{value:l?[u(p[0]),u(p[1])]:u(p),done:m}},[Symbol.iterator](){return this}}}}function Yt(e){return function(...t){return e==="delete"?!1:e==="clear"?void 0:this}}function mi(e,t){const s={get(r){const i=this.__v_raw,o=K(i),l=K(r);e||(ze(r,l)&&_e(o,"get",r),_e(o,"get",l));const{has:a}=Jt(o),d=t?Fs:e?zt:ae;if(a.call(o,r))return d(i.get(r));if(a.call(o,l))return d(i.get(l));i!==o&&i.get(r)},get size(){const r=this.__v_raw;return!e&&_e(K(r),"iterate",lt),r.size},has(r){const i=this.__v_raw,o=K(i),l=K(r);return e||(ze(r,l)&&_e(o,"has",r),_e(o,"has",l)),r===l?i.has(r):i.has(r)||i.has(l)},forEach(r,i){const o=this,l=o.__v_raw,a=K(l),d=t?Fs:e?zt:ae;return!e&&_e(a,"iterate",lt),l.forEach((u,p)=>r.call(i,d(u),d(p),o))}};return me(s,e?{add:Yt("add"),set:Yt("set"),delete:Yt("delete"),clear:Yt("clear")}:{add(r){!t&&!Oe(r)&&!Ze(r)&&(r=K(r));const i=K(this);return Jt(i).has.call(i,r)||(i.add(r),We(i,"add",r,r)),this},set(r,i){!t&&!Oe(i)&&!Ze(i)&&(i=K(i));const o=K(this),{has:l,get:a}=Jt(o);let d=l.call(o,r);d||(r=K(r),d=l.call(o,r));const u=a.call(o,r);return o.set(r,i),d?ze(i,u)&&We(o,"set",r,i):We(o,"add",r,i),this},delete(r){const i=K(this),{has:o,get:l}=Jt(i);let a=o.call(i,r);a||(r=K(r),a=o.call(i,r)),l&&l.call(i,r);const d=i.delete(r);return a&&We(i,"delete",r,void 0),d},clear(){const r=K(this),i=r.size!==0,o=r.clear();return i&&We(r,"clear",void 0,void 0),o}}),["keys","values","entries",Symbol.iterator].forEach(r=>{s[r]=gi(r,e,t)}),s}function Qt(e,t){const s=mi(e,t);return(n,r,i)=>r==="__v_isReactive"?!e:r==="__v_isReadonly"?e:r==="__v_raw"?n:Reflect.get(W(s,r)&&r in n?s:n,r,i)}const bi={get:Qt(!1,!1)},yi={get:Qt(!1,!0)},vi={get:Qt(!0,!1)},xi={get:Qt(!0,!0)},In=new WeakMap,Mn=new WeakMap,Rn=new WeakMap,Fn=new WeakMap;function wi(e){switch(e){case"Object":case"Array":return 1;case"Map":case"Set":case"WeakMap":case"WeakSet":return 2;default:return 0}}function Si(e){return e.__v_skip||!Object.isExtensible(e)?0:wi(Yr(e))}function Pe(e){return Ze(e)?e:Xt(e,!1,di,bi,In)}function ki(e){return Xt(e,!1,hi,yi,Mn)}function $s(e){return Xt(e,!0,pi,vi,Rn)}function gc(e){return Xt(e,!0,_i,xi,Fn)}function Xt(e,t,s,n,r){if(!re(e)||e.__v_raw&&!(t&&e.__v_isReactive))return e;const i=Si(e);if(i===0)return e;const o=r.get(e);if(o)return o;const l=new Proxy(e,i===2?n:s);return r.set(e,l),l}function ht(e){return Ze(e)?ht(e.__v_raw):!!(e&&e.__v_isReactive)}function Ze(e){return!!(e&&e.__v_isReadonly)}function Oe(e){return!!(e&&e.__v_isShallow)}function Ls(e){return e?!!e.__v_raw:!1}function K(e){const t=e&&e.__v_raw;return t?K(t):e}function Ci(e){return!W(e,"__v_skip")&&Object.isExtensible(e)&&dn(e,"__v_skip",!0),e}const ae=e=>re(e)?Pe(e):e,zt=e=>re(e)?$s(e):e;function ue(e){return e?e.__v_isRef===!0:!1}function Ds(e){return Ai(e,!1)}function Ai(e,t){return ue(e)?e:new Ti(e,t)}class Ti{constructor(t,s){this.dep=new Ps,this.__v_isRef=!0,this.__v_isShallow=!1,this._rawValue=s?t:K(t),this._value=s?t:ae(t),this.__v_isShallow=s}get value(){return this.dep.track(),this._value}set value(t){const s=this._rawValue,n=this.__v_isShallow||Oe(t)||Ze(t);t=n?t:K(t),ze(t,s)&&(this._rawValue=t,this._value=n?t:ae(t),this.dep.trigger())}}function Ns(e){return ue(e)?e.value:e}const Ei={get:(e,t,s)=>t==="__v_raw"?e:Ns(Reflect.get(e,t,s)),set:(e,t,s,n)=>{const r=e[t];return ue(r)&&!ue(s)?(r.value=s,!0):Reflect.set(e,t,s,n)}};function $n(e){return ht(e)?e:new Proxy(e,Ei)}class Pi{constructor(t,s,n){this.fn=t,this.setter=s,this._value=void 0,this.dep=new Ps(this),this.__v_isRef=!0,this.deps=void 0,this.depsTail=void 0,this.flags=16,this.globalVersion=At-1,this.next=void 0,this.effect=this,this.__v_isReadonly=!s,this.isSSR=n}notify(){if(this.flags|=16,!(this.flags&8)&&ee!==this)return yn(this,!0),!0}get value(){const t=this.dep.track();return wn(this),t&&(t.version=this.dep.version),this._value}set value(t){this.setter&&this.setter(t)}}function Oi(e,t,s=!1){let n,r;return N(e)?n=e:(n=e.get,r=e.set),new Pi(n,r,s)}const Zt={},es=new WeakMap;let ct;function Ii(e,t=!1,s=ct){if(s){let n=es.get(s);n||es.set(s,n=[]),n.push(e)}}function Mi(e,t,s=Z){const{immediate:n,deep:r,once:i,scheduler:o,augmentJob:l,call:a}=s,d=E=>r?E:Oe(E)||r===!1||r===0?et(E,1):et(E);let u,p,m,v,T=!1,L=!1;if(ue(e)?(p=()=>e.value,T=Oe(e)):ht(e)?(p=()=>d(e),T=!0):D(e)?(L=!0,T=e.some(E=>ht(E)||Oe(E)),p=()=>e.map(E=>{if(ue(E))return E.value;if(ht(E))return d(E);if(N(E))return a?a(E,2):E()})):N(e)?t?p=a?()=>a(e,2):e:p=()=>{if(m){Le();try{m()}finally{De()}}const E=ct;ct=u;try{return a?a(e,3,[v]):e(v)}finally{ct=E}}:p=$e,t&&r){const E=p,G=r===!0?1/0:r;p=()=>et(E(),G)}const se=ii(),C=()=>{u.stop(),se&&se.active&&ms(se.effects,u)};if(i&&t){const E=t;t=(...G)=>{E(...G),C()}}let A=L?new Array(e.length).fill(Zt):Zt;const j=E=>{if(!(!(u.flags&1)||!u.dirty&&!E))if(t){const G=u.run();if(r||T||(L?G.some((O,U)=>ze(O,A[U])):ze(G,A))){m&&m();const O=ct;ct=u;try{const U=[G,A===Zt?void 0:L&&A[0]===Zt?[]:A,v];A=G,a?a(t,3,U):t(...U)}finally{ct=O}}}else u.run()};return l&&l(j),u=new mn(p),u.scheduler=o?()=>o(j,!1):j,v=E=>Ii(E,!1,u),m=u.onStop=()=>{const E=es.get(u);if(E){if(a)a(E,4);else for(const G of E)G();es.delete(u)}},t?n?j(!0):A=u.run():o?o(j.bind(null,!0),!0):u.run(),C.pause=u.pause.bind(u),C.resume=u.resume.bind(u),C.stop=C,C}function et(e,t=1/0,s){if(t<=0||!re(e)||e.__v_skip||(s=s||new Map,(s.get(e)||0)>=t))return e;if(s.set(e,t),t--,ue(e))et(e.value,t,s);else if(D(e))for(let n=0;n<e.length;n++)et(e[n],t,s);else if(ln(e)||dt(e))e.forEach(n=>{et(n,t,s)});else if(un(e)){for(const n in e)et(e[n],t,s);for(const n of Object.getOwnPropertySymbols(e))Object.prototype.propertyIsEnumerable.call(e,n)&&et(e[n],t,s)}return e}/**
* @vue/runtime-core v3.5.22
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/const Pt=[];let js=!1;function mc(e,...t){if(js)return;js=!0,Le();const s=Pt.length?Pt[Pt.length-1].component:null,n=s&&s.appContext.config.warnHandler,r=Ri();if(n)_t(n,s,11,[e+t.map(i=>{var o,l;return(l=(o=i.toString)==null?void 0:o.call(i))!=null?l:JSON.stringify(i)}).join(""),s&&s.proxy,r.map(({vnode:i})=>`at <${Tr(s,i.type)}>`).join(`
`),r]);else{const i=[`[Vue warn]: ${e}`,...t];r.length&&i.push(`
`,...Fi(r)),console.warn(...i)}De(),js=!1}function Ri(){let e=Pt[Pt.length-1];if(!e)return[];const t=[];for(;e;){const s=t[0];s&&s.vnode===e?s.recurseCount++:t.push({vnode:e,recurseCount:0});const n=e.component&&e.component.parent;e=n&&n.vnode}return t}function Fi(e){const t=[];return e.forEach((s,n)=>{t.push(...n===0?[]:[`
`],...$i(s))}),t}function $i({vnode:e,recurseCount:t}){const s=t>0?`... (${t} recursive calls)`:"",n=e.component?e.component.parent==null:!1,r=` at <${Tr(e.component,e.type,n)}`,i=">"+s;return e.props?[r,...Li(e.props),i]:[r+i]}function Li(e){const t=[],s=Object.keys(e);return s.slice(0,3).forEach(n=>{t.push(...Ln(n,e[n]))}),s.length>3&&t.push(" ..."),t}function Ln(e,t,s){return oe(t)?(t=JSON.stringify(t),s?t:[`${e}=${t}`]):typeof t=="number"||typeof t=="boolean"||t==null?s?t:[`${e}=${t}`]:ue(t)?(t=Ln(e,K(t.value),!0),s?t:[`${e}=Ref<`,t,">"]):N(t)?[`${e}=fn${t.name?`<${t.name}>`:""}`]:(t=K(t),s?t:[`${e}=`,t])}function _t(e,t,s,n){try{return n?e(...n):e()}catch(r){ts(r,t,s)}}function Ne(e,t,s,n){if(N(e)){const r=_t(e,t,s,n);return r&&cn(r)&&r.catch(i=>{ts(i,t,s)}),r}if(D(e)){const r=[];for(let i=0;i<e.length;i++)r.push(Ne(e[i],t,s,n));return r}}function ts(e,t,s,n=!0){const r=t?t.vnode:null,{errorHandler:i,throwUnhandledErrorInProduction:o}=t&&t.appContext.config||Z;if(t){let l=t.parent;const a=t.proxy,d=`https://vuejs.org/error-reference/#runtime-${s}`;for(;l;){const u=l.ec;if(u){for(let p=0;p<u.length;p++)if(u[p](e,a,d)===!1)return}l=l.parent}if(i){Le(),_t(i,null,10,[e,a,d]),De();return}}Di(e,s,r,n,o)}function Di(e,t,s,n=!0,r=!1){if(r)throw e;console.error(e)}const be=[];let je=-1;const gt=[];let tt=null,mt=0;const Dn=Promise.resolve();let ss=null;function Ni(e){const t=ss||Dn;return e?t.then(this?e.bind(this):e):t}function ji(e){let t=je+1,s=be.length;for(;t<s;){const n=t+s>>>1,r=be[n],i=Ot(r);i<e||i===e&&r.flags&2?t=n+1:s=n}return t}function Hs(e){if(!(e.flags&1)){const t=Ot(e),s=be[be.length-1];!s||!(e.flags&2)&&t>=Ot(s)?be.push(e):be.splice(ji(t),0,e),e.flags|=1,Nn()}}function Nn(){ss||(ss=Dn.then(Un))}function Hi(e){D(e)?gt.push(...e):tt&&e.id===-1?tt.splice(mt+1,0,e):e.flags&1||(gt.push(e),e.flags|=1),Nn()}function jn(e,t,s=je+1){for(;s<be.length;s++){const n=be[s];if(n&&n.flags&2){if(e&&n.id!==e.uid)continue;be.splice(s,1),s--,n.flags&4&&(n.flags&=-2),n(),n.flags&4||(n.flags&=-2)}}}function Hn(e){if(gt.length){const t=[...new Set(gt)].sort((s,n)=>Ot(s)-Ot(n));if(gt.length=0,tt){tt.push(...t);return}for(tt=t,mt=0;mt<tt.length;mt++){const s=tt[mt];s.flags&4&&(s.flags&=-2),s.flags&8||s(),s.flags&=-2}tt=null,mt=0}}const Ot=e=>e.id==null?e.flags&2?-1:1/0:e.id;function Un(e){try{for(je=0;je<be.length;je++){const t=be[je];t&&!(t.flags&8)&&(t.flags&4&&(t.flags&=-2),_t(t,t.i,t.i?15:14),t.flags&4||(t.flags&=-2))}}finally{for(;je<be.length;je++){const t=be[je];t&&(t.flags&=-2)}je=-1,be.length=0,Hn(),ss=null,(be.length||gt.length)&&Un()}}let He=null,qn=null;function ns(e){const t=He;return He=e,qn=e&&e.type.__scopeId||null,t}function Ui(e,t=He,s){if(!t||e._n)return e;const n=(...r)=>{n._d&&yr(-1);const i=ns(t);let o;try{o=e(...r)}finally{ns(i),n._d&&yr(1)}return o};return n._n=!0,n._c=!0,n._d=!0,n}function at(e,t,s,n){const r=e.dirs,i=t&&t.dirs;for(let o=0;o<r.length;o++){const l=r[o];i&&(l.oldValue=i[o].value);let a=l.dir[n];a&&(Le(),Ne(a,s,8,[e.el,l,e,t]),De())}}const qi=Symbol("_vte"),Vi=e=>e.__isTeleport,Bi=Symbol("_leaveCb");function Us(e,t){e.shapeFlag&6&&e.component?(e.transition=t,Us(e.component.subTree,t)):e.shapeFlag&128?(e.ssContent.transition=t.clone(e.ssContent),e.ssFallback.transition=t.clone(e.ssFallback)):e.transition=t}function Vn(e){e.ids=[e.ids[0]+e.ids[2]+++"-",0,0]}const rs=new WeakMap;function It(e,t,s,n,r=!1){if(D(e)){e.forEach((T,L)=>It(T,t&&(D(t)?t[L]:t),s,n,r));return}if(Mt(n)&&!r){n.shapeFlag&512&&n.type.__asyncResolved&&n.component.subTree.component&&It(e,t,s,n.component.subTree);return}const i=n.shapeFlag&4?en(n.component):n.el,o=r?null:i,{i:l,r:a}=e,d=t&&t.r,u=l.refs===Z?l.refs={}:l.refs,p=l.setupState,m=K(p),v=p===Z?on:T=>W(m,T);if(d!=null&&d!==a){if(Bn(t),oe(d))u[d]=null,v(d)&&(p[d]=null);else if(ue(d)){d.value=null;const T=t;T.k&&(u[T.k]=null)}}if(N(a))_t(a,l,12,[o,u]);else{const T=oe(a),L=ue(a);if(T||L){const se=()=>{if(e.f){const C=T?v(a)?p[a]:u[a]:a.value;if(r)D(C)&&ms(C,i);else if(D(C))C.includes(i)||C.push(i);else if(T)u[a]=[i],v(a)&&(p[a]=u[a]);else{const A=[i];a.value=A,e.k&&(u[e.k]=A)}}else T?(u[a]=o,v(a)&&(p[a]=o)):L&&(a.value=o,e.k&&(u[e.k]=o))};if(o){const C=()=>{se(),rs.delete(e)};C.id=-1,rs.set(e,C),ke(C,s)}else Bn(e),se()}}}function Bn(e){const t=rs.get(e);t&&(t.flags|=8,rs.delete(e))}Wt().requestIdleCallback,Wt().cancelIdleCallback;const Mt=e=>!!e.type.__asyncLoader,Kn=e=>e.type.__isKeepAlive;function Ki(e,t){Wn(e,"a",t)}function Wi(e,t){Wn(e,"da",t)}function Wn(e,t,s=ve){const n=e.__wdc||(e.__wdc=()=>{let r=s;for(;r;){if(r.isDeactivated)return;r=r.parent}return e()});if(is(t,n,s),s){let r=s.parent;for(;r&&r.parent;)Kn(r.parent.vnode)&&Gi(n,t,s,r),r=r.parent}}function Gi(e,t,s,n){const r=is(t,e,n,!0);Gn(()=>{ms(n[t],r)},s)}function is(e,t,s=ve,n=!1){if(s){const r=s[e]||(s[e]=[]),i=t.__weh||(t.__weh=(...o)=>{Le();const l=Nt(s),a=Ne(t,s,e,o);return l(),De(),a});return n?r.unshift(i):r.push(i),i}}const Je=e=>(t,s=ve)=>{(!jt||e==="sp")&&is(e,(...n)=>t(...n),s)},Ji=Je("bm"),Yi=Je("m"),Qi=Je("bu"),Xi=Je("u"),zi=Je("bum"),Gn=Je("um"),Zi=Je("sp"),eo=Je("rtg"),to=Je("rtc");function so(e,t=ve){is("ec",e,t)}const no=Symbol.for("v-ndc");function qs(e,t,s,n){let r;const i=s,o=D(e);if(o||oe(e)){const l=o&&ht(e);let a=!1,d=!1;l&&(a=!Oe(e),d=Ze(e),e=Gt(e)),r=new Array(e.length);for(let u=0,p=e.length;u<p;u++)r[u]=t(a?d?zt(ae(e[u])):ae(e[u]):e[u],u,void 0,i)}else if(typeof e=="number"){r=new Array(e);for(let l=0;l<e;l++)r[l]=t(l+1,l,void 0,i)}else if(re(e))if(e[Symbol.iterator])r=Array.from(e,(l,a)=>t(l,a,void 0,i));else{const l=Object.keys(e);r=new Array(l.length);for(let a=0,d=l.length;a<d;a++){const u=l[a];r[a]=t(e[u],u,a,i)}}else r=[];return r}const Vs=e=>e?kr(e)?en(e):Vs(e.parent):null,Rt=me(Object.create(null),{$:e=>e,$el:e=>e.vnode.el,$data:e=>e.data,$props:e=>e.props,$attrs:e=>e.attrs,$slots:e=>e.slots,$refs:e=>e.refs,$parent:e=>Vs(e.parent),$root:e=>Vs(e.root),$host:e=>e.ce,$emit:e=>e.emit,$options:e=>Xn(e),$forceUpdate:e=>e.f||(e.f=()=>{Hs(e.update)}),$nextTick:e=>e.n||(e.n=Ni.bind(e.proxy)),$watch:e=>Ao.bind(e)}),Bs=(e,t)=>e!==Z&&!e.__isScriptSetup&&W(e,t),ro={get({_:e},t){if(t==="__v_skip")return!0;const{ctx:s,setupState:n,data:r,props:i,accessCache:o,type:l,appContext:a}=e;let d;if(t[0]!=="$"){const v=o[t];if(v!==void 0)switch(v){case 1:return n[t];case 2:return r[t];case 4:return s[t];case 3:return i[t]}else{if(Bs(n,t))return o[t]=1,n[t];if(r!==Z&&W(r,t))return o[t]=2,r[t];if((d=e.propsOptions[0])&&W(d,t))return o[t]=3,i[t];if(s!==Z&&W(s,t))return o[t]=4,s[t];Ks&&(o[t]=0)}}const u=Rt[t];let p,m;if(u)return t==="$attrs"&&_e(e.attrs,"get",""),u(e);if((p=l.__cssModules)&&(p=p[t]))return p;if(s!==Z&&W(s,t))return o[t]=4,s[t];if(m=a.config.globalProperties,W(m,t))return m[t]},set({_:e},t,s){const{data:n,setupState:r,ctx:i}=e;return Bs(r,t)?(r[t]=s,!0):n!==Z&&W(n,t)?(n[t]=s,!0):W(e.props,t)||t[0]==="$"&&t.slice(1)in e?!1:(i[t]=s,!0)},has({_:{data:e,setupState:t,accessCache:s,ctx:n,appContext:r,propsOptions:i,type:o}},l){let a,d;return!!(s[l]||e!==Z&&l[0]!=="$"&&W(e,l)||Bs(t,l)||(a=i[0])&&W(a,l)||W(n,l)||W(Rt,l)||W(r.config.globalProperties,l)||(d=o.__cssModules)&&d[l])},defineProperty(e,t,s){return s.get!=null?e._.accessCache[t]=0:W(s,"value")&&this.set(e,t,s.value,null),Reflect.defineProperty(e,t,s)}};function Jn(e){return D(e)?e.reduce((t,s)=>(t[s]=null,t),{}):e}let Ks=!0;function io(e){const t=Xn(e),s=e.proxy,n=e.ctx;Ks=!1,t.beforeCreate&&Yn(t.beforeCreate,e,"bc");const{data:r,computed:i,methods:o,watch:l,provide:a,inject:d,created:u,beforeMount:p,mounted:m,beforeUpdate:v,updated:T,activated:L,deactivated:se,beforeDestroy:C,beforeUnmount:A,destroyed:j,unmounted:E,render:G,renderTracked:O,renderTriggered:U,errorCaptured:ne,serverPrefetch:de,expose:Y,inheritAttrs:le,components:I,directives:Q,filters:ce}=t;if(d&&oo(d,n,null),o)for(const q in o){const H=o[q];N(H)&&(n[q]=H.bind(s))}if(r){const q=r.call(s,s);re(q)&&(e.data=Pe(q))}if(Ks=!0,i)for(const q in i){const H=i[q],pe=N(H)?H.bind(s,s):N(H.get)?H.get.bind(s,s):$e,F=!N(H)&&N(H.set)?H.set.bind(s):$e,V=X({get:pe,set:F});Object.defineProperty(n,q,{enumerable:!0,configurable:!0,get:()=>V.value,set:z=>V.value=z})}if(l)for(const q in l)Qn(l[q],n,s,q);if(a){const q=N(a)?a.call(s):a;Reflect.ownKeys(q).forEach(H=>{po(H,q[H])})}u&&Yn(u,e,"c");function P(q,H){D(H)?H.forEach(pe=>q(pe.bind(s))):H&&q(H.bind(s))}if(P(Ji,p),P(Yi,m),P(Qi,v),P(Xi,T),P(Ki,L),P(Wi,se),P(so,ne),P(to,O),P(eo,U),P(zi,A),P(Gn,E),P(Zi,de),D(Y))if(Y.length){const q=e.exposed||(e.exposed={});Y.forEach(H=>{Object.defineProperty(q,H,{get:()=>s[H],set:pe=>s[H]=pe,enumerable:!0})})}else e.exposed||(e.exposed={});G&&e.render===$e&&(e.render=G),le!=null&&(e.inheritAttrs=le),I&&(e.components=I),Q&&(e.directives=Q),de&&Vn(e)}function oo(e,t,s=$e){D(e)&&(e=Ws(e));for(const n in e){const r=e[n];let i;re(r)?"default"in r?i=ls(r.from||n,r.default,!0):i=ls(r.from||n):i=ls(r),ue(i)?Object.defineProperty(t,n,{enumerable:!0,configurable:!0,get:()=>i.value,set:o=>i.value=o}):t[n]=i}}function Yn(e,t,s){Ne(D(e)?e.map(n=>n.bind(t.proxy)):e.bind(t.proxy),t,s)}function Qn(e,t,s,n){let r=n.includes(".")?hr(s,n):()=>s[n];if(oe(e)){const i=t[e];N(i)&&yt(r,i)}else if(N(e))yt(r,e.bind(s));else if(re(e))if(D(e))e.forEach(i=>Qn(i,t,s,n));else{const i=N(e.handler)?e.handler.bind(s):t[e.handler];N(i)&&yt(r,i,e)}}function Xn(e){const t=e.type,{mixins:s,extends:n}=t,{mixins:r,optionsCache:i,config:{optionMergeStrategies:o}}=e.appContext,l=i.get(t);let a;return l?a=l:!r.length&&!s&&!n?a=t:(a={},r.length&&r.forEach(d=>os(a,d,o,!0)),os(a,t,o)),re(t)&&i.set(t,a),a}function os(e,t,s,n=!1){const{mixins:r,extends:i}=t;i&&os(e,i,s,!0),r&&r.forEach(o=>os(e,o,s,!0));for(const o in t)if(!(n&&o==="expose")){const l=lo[o]||s&&s[o];e[o]=l?l(e[o],t[o]):t[o]}return e}const lo={data:zn,props:Zn,emits:Zn,methods:Ft,computed:Ft,beforeCreate:ye,created:ye,beforeMount:ye,mounted:ye,beforeUpdate:ye,updated:ye,beforeDestroy:ye,beforeUnmount:ye,destroyed:ye,unmounted:ye,activated:ye,deactivated:ye,errorCaptured:ye,serverPrefetch:ye,components:Ft,directives:Ft,watch:ao,provide:zn,inject:co};function zn(e,t){return t?e?function(){return me(N(e)?e.call(this,this):e,N(t)?t.call(this,this):t)}:t:e}function co(e,t){return Ft(Ws(e),Ws(t))}function Ws(e){if(D(e)){const t={};for(let s=0;s<e.length;s++)t[e[s]]=e[s];return t}return e}function ye(e,t){return e?[...new Set([].concat(e,t))]:t}function Ft(e,t){return e?me(Object.create(null),e,t):t}function Zn(e,t){return e?D(e)&&D(t)?[...new Set([...e,...t])]:me(Object.create(null),Jn(e),Jn(t!=null?t:{})):t}function ao(e,t){if(!e)return t;if(!t)return e;const s=me(Object.create(null),e);for(const n in t)s[n]=ye(e[n],t[n]);return s}function er(){return{app:null,config:{isNativeTag:on,performance:!1,globalProperties:{},optionMergeStrategies:{},errorHandler:void 0,warnHandler:void 0,compilerOptions:{}},mixins:[],components:{},directives:{},provides:Object.create(null),optionsCache:new WeakMap,propsCache:new WeakMap,emitsCache:new WeakMap}}let uo=0;function fo(e,t){return function(n,r=null){N(n)||(n=me({},n)),r!=null&&!re(r)&&(r=null);const i=er(),o=new WeakSet,l=[];let a=!1;const d=i.app={_uid:uo++,_component:n,_props:r,_container:null,_context:i,_instance:null,version:Zo,get config(){return i.config},set config(u){},use(u,...p){return o.has(u)||(u&&N(u.install)?(o.add(u),u.install(d,...p)):N(u)&&(o.add(u),u(d,...p))),d},mixin(u){return i.mixins.includes(u)||i.mixins.push(u),d},component(u,p){return p?(i.components[u]=p,d):i.components[u]},directive(u,p){return p?(i.directives[u]=p,d):i.directives[u]},mount(u,p,m){if(!a){const v=d._ceVNode||Ae(n,r);return v.appContext=i,m===!0?m="svg":m===!1&&(m=void 0),e(v,u,m),a=!0,d._container=u,u.__vue_app__=d,en(v.component)}},onUnmount(u){l.push(u)},unmount(){a&&(Ne(l,d._instance,16),e(null,d._container),delete d._container.__vue_app__)},provide(u,p){return i.provides[u]=p,d},runWithContext(u){const p=bt;bt=d;try{return u()}finally{bt=p}}};return d}}let bt=null;function po(e,t){if(ve){let s=ve.provides;const n=ve.parent&&ve.parent.provides;n===s&&(s=ve.provides=Object.create(n)),s[e]=t}}function ls(e,t,s=!1){const n=Bo();if(n||bt){let r=bt?bt._context.provides:n?n.parent==null||n.ce?n.vnode.appContext&&n.vnode.appContext.provides:n.parent.provides:void 0;if(r&&e in r)return r[e];if(arguments.length>1)return s&&N(t)?t.call(n&&n.proxy):t}}const tr={},sr=()=>Object.create(tr),nr=e=>Object.getPrototypeOf(e)===tr;function ho(e,t,s,n=!1){const r={},i=sr();e.propsDefaults=Object.create(null),rr(e,t,r,i);for(const o in e.propsOptions[0])o in r||(r[o]=void 0);s?e.props=n?r:ki(r):e.type.props?e.props=r:e.props=i,e.attrs=i}function _o(e,t,s,n){const{props:r,attrs:i,vnode:{patchFlag:o}}=e,l=K(r),[a]=e.propsOptions;let d=!1;if((n||o>0)&&!(o&16)){if(o&8){const u=e.vnode.dynamicProps;for(let p=0;p<u.length;p++){let m=u[p];if(cs(e.emitsOptions,m))continue;const v=t[m];if(a)if(W(i,m))v!==i[m]&&(i[m]=v,d=!0);else{const T=Xe(m);r[T]=Gs(a,l,T,v,e,!1)}else v!==i[m]&&(i[m]=v,d=!0)}}}else{rr(e,t,r,i)&&(d=!0);let u;for(const p in l)(!t||!W(t,p)&&((u=ot(p))===p||!W(t,u)))&&(a?s&&(s[p]!==void 0||s[u]!==void 0)&&(r[p]=Gs(a,l,p,void 0,e,!0)):delete r[p]);if(i!==l)for(const p in i)(!t||!W(t,p))&&(delete i[p],d=!0)}d&&We(e.attrs,"set","")}function rr(e,t,s,n){const[r,i]=e.propsOptions;let o=!1,l;if(t)for(let a in t){if(St(a))continue;const d=t[a];let u;r&&W(r,u=Xe(a))?!i||!i.includes(u)?s[u]=d:(l||(l={}))[u]=d:cs(e.emitsOptions,a)||(!(a in n)||d!==n[a])&&(n[a]=d,o=!0)}if(i){const a=K(s),d=l||Z;for(let u=0;u<i.length;u++){const p=i[u];s[p]=Gs(r,a,p,d[p],e,!W(d,p))}}return o}function Gs(e,t,s,n,r,i){const o=e[s];if(o!=null){const l=W(o,"default");if(l&&n===void 0){const a=o.default;if(o.type!==Function&&!o.skipFactory&&N(a)){const{propsDefaults:d}=r;if(s in d)n=d[s];else{const u=Nt(r);n=d[s]=a.call(null,t),u()}}else n=a;r.ce&&r.ce._setProp(s,n)}o[0]&&(i&&!l?n=!1:o[1]&&(n===""||n===ot(s))&&(n=!0))}return n}const go=new WeakMap;function ir(e,t,s=!1){const n=s?go:t.propsCache,r=n.get(e);if(r)return r;const i=e.props,o={},l=[];let a=!1;if(!N(e)){const u=p=>{a=!0;const[m,v]=ir(p,t,!0);me(o,m),v&&l.push(...v)};!s&&t.mixins.length&&t.mixins.forEach(u),e.extends&&u(e.extends),e.mixins&&e.mixins.forEach(u)}if(!i&&!a)return re(e)&&n.set(e,ft),ft;if(D(i))for(let u=0;u<i.length;u++){const p=Xe(i[u]);or(p)&&(o[p]=Z)}else if(i)for(const u in i){const p=Xe(u);if(or(p)){const m=i[u],v=o[p]=D(m)||N(m)?{type:m}:me({},m),T=v.type;let L=!1,se=!0;if(D(T))for(let C=0;C<T.length;++C){const A=T[C],j=N(A)&&A.name;if(j==="Boolean"){L=!0;break}else j==="String"&&(se=!1)}else L=N(T)&&T.name==="Boolean";v[0]=L,v[1]=se,(L||W(v,"default"))&&l.push(p)}}const d=[o,l];return re(e)&&n.set(e,d),d}function or(e){return e[0]!=="$"&&!St(e)}const Js=e=>e==="_"||e==="_ctx"||e==="$stable",Ys=e=>D(e)?e.map(Ue):[Ue(e)],mo=(e,t,s)=>{if(t._n)return t;const n=Ui((...r)=>Ys(t(...r)),s);return n._c=!1,n},lr=(e,t,s)=>{const n=e._ctx;for(const r in e){if(Js(r))continue;const i=e[r];if(N(i))t[r]=mo(r,i,n);else if(i!=null){const o=Ys(i);t[r]=()=>o}}},cr=(e,t)=>{const s=Ys(t);e.slots.default=()=>s},ar=(e,t,s)=>{for(const n in t)(s||!Js(n))&&(e[n]=t[n])},bo=(e,t,s)=>{const n=e.slots=sr();if(e.vnode.shapeFlag&32){const r=t._;r?(ar(n,t,s),s&&dn(n,"_",r,!0)):lr(t,n)}else t&&cr(e,t)},yo=(e,t,s)=>{const{vnode:n,slots:r}=e;let i=!0,o=Z;if(n.shapeFlag&32){const l=t._;l?s&&l===1?i=!1:ar(r,t,s):(i=!t.$stable,lr(t,r)),o=t}else t&&(cr(e,t),o={default:1});if(i)for(const l in r)!Js(l)&&o[l]==null&&delete r[l]},ke=Fo;function vo(e){return xo(e)}function xo(e,t){const s=Wt();s.__VUE__=!0;const{insert:n,remove:r,patchProp:i,createElement:o,createText:l,createComment:a,setText:d,setElementText:u,parentNode:p,nextSibling:m,setScopeId:v=$e,insertStaticContent:T}=e,L=(c,f,h,b=null,_=null,g=null,S=void 0,w=null,x=!!f.dynamicChildren)=>{if(c===f)return;c&&!Dt(c,f)&&(b=hs(c),z(c,_,g,!0),c=null),f.patchFlag===-2&&(x=!1,f.dynamicChildren=null);const{type:y,ref:R,shapeFlag:k}=f;switch(y){case as:se(c,f,h,b);break;case st:C(c,f,h,b);break;case Xs:c==null&&A(f,h,b,S);break;case Ie:I(c,f,h,b,_,g,S,w,x);break;default:k&1?G(c,f,h,b,_,g,S,w,x):k&6?Q(c,f,h,b,_,g,S,w,x):(k&64||k&128)&&y.process(c,f,h,b,_,g,S,w,x,Ut)}R!=null&&_?It(R,c&&c.ref,g,f||c,!f):R==null&&c&&c.ref!=null&&It(c.ref,null,g,c,!0)},se=(c,f,h,b)=>{if(c==null)n(f.el=l(f.children),h,b);else{const _=f.el=c.el;f.children!==c.children&&d(_,f.children)}},C=(c,f,h,b)=>{c==null?n(f.el=a(f.children||""),h,b):f.el=c.el},A=(c,f,h,b)=>{[c.el,c.anchor]=T(c.children,f,h,b,c.el,c.anchor)},j=({el:c,anchor:f},h,b)=>{let _;for(;c&&c!==f;)_=m(c),n(c,h,b),c=_;n(f,h,b)},E=({el:c,anchor:f})=>{let h;for(;c&&c!==f;)h=m(c),r(c),c=h;r(f)},G=(c,f,h,b,_,g,S,w,x)=>{f.type==="svg"?S="svg":f.type==="math"&&(S="mathml"),c==null?O(f,h,b,_,g,S,w,x):de(c,f,_,g,S,w,x)},O=(c,f,h,b,_,g,S,w)=>{let x,y;const{props:R,shapeFlag:k,transition:M,dirs:$}=c;if(x=c.el=o(c.type,g,R&&R.is,R),k&8?u(x,c.children):k&16&&ne(c.children,x,null,b,_,Qs(c,g),S,w),$&&at(c,null,b,"created"),U(x,c,c.scopeId,S,b),R){for(const te in R)te!=="value"&&!St(te)&&i(x,te,null,R[te],g,b);"value"in R&&i(x,"value",null,R.value,g),(y=R.onVnodeBeforeMount)&&qe(y,b,c)}$&&at(c,null,b,"beforeMount");const B=wo(_,M);B&&M.beforeEnter(x),n(x,f,h),((y=R&&R.onVnodeMounted)||B||$)&&ke(()=>{y&&qe(y,b,c),B&&M.enter(x),$&&at(c,null,b,"mounted")},_)},U=(c,f,h,b,_)=>{if(h&&v(c,h),b)for(let g=0;g<b.length;g++)v(c,b[g]);if(_){let g=_.subTree;if(f===g||br(g.type)&&(g.ssContent===f||g.ssFallback===f)){const S=_.vnode;U(c,S,S.scopeId,S.slotScopeIds,_.parent)}}},ne=(c,f,h,b,_,g,S,w,x=0)=>{for(let y=x;y<c.length;y++){const R=c[y]=w?rt(c[y]):Ue(c[y]);L(null,R,f,h,b,_,g,S,w)}},de=(c,f,h,b,_,g,S)=>{const w=f.el=c.el;let{patchFlag:x,dynamicChildren:y,dirs:R}=f;x|=c.patchFlag&16;const k=c.props||Z,M=f.props||Z;let $;if(h&&ut(h,!1),($=M.onVnodeBeforeUpdate)&&qe($,h,f,c),R&&at(f,c,h,"beforeUpdate"),h&&ut(h,!0),(k.innerHTML&&M.innerHTML==null||k.textContent&&M.textContent==null)&&u(w,""),y?Y(c.dynamicChildren,y,w,h,b,Qs(f,_),g):S||H(c,f,w,null,h,b,Qs(f,_),g,!1),x>0){if(x&16)le(w,k,M,h,_);else if(x&2&&k.class!==M.class&&i(w,"class",null,M.class,_),x&4&&i(w,"style",k.style,M.style,_),x&8){const B=f.dynamicProps;for(let te=0;te<B.length;te++){const J=B[te],we=k[J],Se=M[J];(Se!==we||J==="value")&&i(w,J,we,Se,_,h)}}x&1&&c.children!==f.children&&u(w,f.children)}else!S&&y==null&&le(w,k,M,h,_);(($=M.onVnodeUpdated)||R)&&ke(()=>{$&&qe($,h,f,c),R&&at(f,c,h,"updated")},b)},Y=(c,f,h,b,_,g,S)=>{for(let w=0;w<f.length;w++){const x=c[w],y=f[w],R=x.el&&(x.type===Ie||!Dt(x,y)||x.shapeFlag&198)?p(x.el):h;L(x,y,R,null,b,_,g,S,!0)}},le=(c,f,h,b,_)=>{if(f!==h){if(f!==Z)for(const g in f)!St(g)&&!(g in h)&&i(c,g,f[g],null,_,b);for(const g in h){if(St(g))continue;const S=h[g],w=f[g];S!==w&&g!=="value"&&i(c,g,w,S,_,b)}"value"in h&&i(c,"value",f.value,h.value,_)}},I=(c,f,h,b,_,g,S,w,x)=>{const y=f.el=c?c.el:l(""),R=f.anchor=c?c.anchor:l("");let{patchFlag:k,dynamicChildren:M,slotScopeIds:$}=f;$&&(w=w?w.concat($):$),c==null?(n(y,h,b),n(R,h,b),ne(f.children||[],h,R,_,g,S,w,x)):k>0&&k&64&&M&&c.dynamicChildren?(Y(c.dynamicChildren,M,h,_,g,S,w),(f.key!=null||_&&f===_.subTree)&&ur(c,f,!0)):H(c,f,h,R,_,g,S,w,x)},Q=(c,f,h,b,_,g,S,w,x)=>{f.slotScopeIds=w,c==null?f.shapeFlag&512?_.ctx.activate(f,h,b,S,x):ce(f,h,b,_,g,S,x):Me(c,f,x)},ce=(c,f,h,b,_,g,S)=>{const w=c.component=Vo(c,b,_);if(Kn(c)&&(w.ctx.renderer=Ut),Ko(w,!1,S),w.asyncDep){if(_&&_.registerDep(w,P,S),!c.el){const x=w.subTree=Ae(st);C(null,x,f,h),c.placeholder=x.el}}else P(w,c,f,h,_,g,S)},Me=(c,f,h)=>{const b=f.component=c.component;if(Mo(c,f,h))if(b.asyncDep&&!b.asyncResolved){q(b,f,h);return}else b.next=f,b.update();else f.el=c.el,b.vnode=f},P=(c,f,h,b,_,g,S)=>{const w=()=>{if(c.isMounted){let{next:k,bu:M,u:$,parent:B,vnode:te}=c;{const Be=fr(c);if(Be){k&&(k.el=te.el,q(c,k,S)),Be.asyncDep.then(()=>{c.isUnmounted||w()});return}}let J=k,we;ut(c,!1),k?(k.el=te.el,q(c,k,S)):k=te,M&&vs(M),(we=k.props&&k.props.onVnodeBeforeUpdate)&&qe(we,B,k,te),ut(c,!0);const Se=gr(c),Ve=c.subTree;c.subTree=Se,L(Ve,Se,p(Ve.el),hs(Ve),c,_,g),k.el=Se.el,J===null&&Ro(c,Se.el),$&&ke($,_),(we=k.props&&k.props.onVnodeUpdated)&&ke(()=>qe(we,B,k,te),_)}else{let k;const{el:M,props:$}=f,{bm:B,m:te,parent:J,root:we,type:Se}=c,Ve=Mt(f);ut(c,!1),B&&vs(B),!Ve&&(k=$&&$.onVnodeBeforeMount)&&qe(k,J,f),ut(c,!0);{we.ce&&we.ce._def.shadowRoot!==!1&&we.ce._injectChildStyle(Se);const Be=c.subTree=gr(c);L(null,Be,h,b,c,_,g),f.el=Be.el}if(te&&ke(te,_),!Ve&&(k=$&&$.onVnodeMounted)){const Be=f;ke(()=>qe(k,J,Be),_)}(f.shapeFlag&256||J&&Mt(J.vnode)&&J.vnode.shapeFlag&256)&&c.a&&ke(c.a,_),c.isMounted=!0,f=h=b=null}};c.scope.on();const x=c.effect=new mn(w);c.scope.off();const y=c.update=x.run.bind(x),R=c.job=x.runIfDirty.bind(x);R.i=c,R.id=c.uid,x.scheduler=()=>Hs(R),ut(c,!0),y()},q=(c,f,h)=>{f.component=c;const b=c.vnode.props;c.vnode=f,c.next=null,_o(c,f.props,b,h),yo(c,f.children,h),Le(),jn(c),De()},H=(c,f,h,b,_,g,S,w,x=!1)=>{const y=c&&c.children,R=c?c.shapeFlag:0,k=f.children,{patchFlag:M,shapeFlag:$}=f;if(M>0){if(M&128){F(y,k,h,b,_,g,S,w,x);return}else if(M&256){pe(y,k,h,b,_,g,S,w,x);return}}$&8?(R&16&&it(y,_,g),k!==y&&u(h,k)):R&16?$&16?F(y,k,h,b,_,g,S,w,x):it(y,_,g,!0):(R&8&&u(h,""),$&16&&ne(k,h,b,_,g,S,w,x))},pe=(c,f,h,b,_,g,S,w,x)=>{c=c||ft,f=f||ft;const y=c.length,R=f.length,k=Math.min(y,R);let M;for(M=0;M<k;M++){const $=f[M]=x?rt(f[M]):Ue(f[M]);L(c[M],$,h,null,_,g,S,w,x)}y>R?it(c,_,g,!0,!1,k):ne(f,h,b,_,g,S,w,x,k)},F=(c,f,h,b,_,g,S,w,x)=>{let y=0;const R=f.length;let k=c.length-1,M=R-1;for(;y<=k&&y<=M;){const $=c[y],B=f[y]=x?rt(f[y]):Ue(f[y]);if(Dt($,B))L($,B,h,null,_,g,S,w,x);else break;y++}for(;y<=k&&y<=M;){const $=c[k],B=f[M]=x?rt(f[M]):Ue(f[M]);if(Dt($,B))L($,B,h,null,_,g,S,w,x);else break;k--,M--}if(y>k){if(y<=M){const $=M+1,B=$<R?f[$].el:b;for(;y<=M;)L(null,f[y]=x?rt(f[y]):Ue(f[y]),h,B,_,g,S,w,x),y++}}else if(y>M)for(;y<=k;)z(c[y],_,g,!0),y++;else{const $=y,B=y,te=new Map;for(y=B;y<=M;y++){const Ee=f[y]=x?rt(f[y]):Ue(f[y]);Ee.key!=null&&te.set(Ee.key,y)}let J,we=0;const Se=M-B+1;let Ve=!1,Be=0;const qt=new Array(Se);for(y=0;y<Se;y++)qt[y]=0;for(y=$;y<=k;y++){const Ee=c[y];if(we>=Se){z(Ee,_,g,!0);continue}let Ke;if(Ee.key!=null)Ke=te.get(Ee.key);else for(J=B;J<=M;J++)if(qt[J-B]===0&&Dt(Ee,f[J])){Ke=J;break}Ke===void 0?z(Ee,_,g,!0):(qt[Ke-B]=y+1,Ke>=Be?Be=Ke:Ve=!0,L(Ee,f[Ke],h,null,_,g,S,w,x),we++)}const Kr=Ve?So(qt):ft;for(J=Kr.length-1,y=Se-1;y>=0;y--){const Ee=B+y,Ke=f[Ee],Wr=f[Ee+1],Gr=Ee+1<R?Wr.el||Wr.placeholder:b;qt[y]===0?L(null,Ke,h,Gr,_,g,S,w,x):Ve&&(J<0||y!==Kr[J]?V(Ke,h,Gr,2):J--)}}},V=(c,f,h,b,_=null)=>{const{el:g,type:S,transition:w,children:x,shapeFlag:y}=c;if(y&6){V(c.component.subTree,f,h,b);return}if(y&128){c.suspense.move(f,h,b);return}if(y&64){S.move(c,f,h,Ut);return}if(S===Ie){n(g,f,h);for(let k=0;k<x.length;k++)V(x[k],f,h,b);n(c.anchor,f,h);return}if(S===Xs){j(c,f,h);return}if(b!==2&&y&1&&w)if(b===0)w.beforeEnter(g),n(g,f,h),ke(()=>w.enter(g),_);else{const{leave:k,delayLeave:M,afterLeave:$}=w,B=()=>{c.ctx.isUnmounted?r(g):n(g,f,h)},te=()=>{g._isLeaving&&g[Bi](!0),k(g,()=>{B(),$&&$()})};M?M(g,B,te):te()}else n(g,f,h)},z=(c,f,h,b=!1,_=!1)=>{const{type:g,props:S,ref:w,children:x,dynamicChildren:y,shapeFlag:R,patchFlag:k,dirs:M,cacheIndex:$}=c;if(k===-2&&(_=!1),w!=null&&(Le(),It(w,null,h,c,!0),De()),$!=null&&(f.renderCache[$]=void 0),R&256){f.ctx.deactivate(c);return}const B=R&1&&M,te=!Mt(c);let J;if(te&&(J=S&&S.onVnodeBeforeUnmount)&&qe(J,f,c),R&6)ps(c.component,h,b);else{if(R&128){c.suspense.unmount(h,b);return}B&&at(c,null,f,"beforeUnmount"),R&64?c.type.remove(c,f,h,Ut,b):y&&!y.hasOnce&&(g!==Ie||k>0&&k&64)?it(y,f,h,!1,!0):(g===Ie&&k&384||!_&&R&16)&&it(x,f,h),b&&Te(c)}(te&&(J=S&&S.onVnodeUnmounted)||B)&&ke(()=>{J&&qe(J,f,c),B&&at(c,null,f,"unmounted")},h)},Te=c=>{const{type:f,el:h,anchor:b,transition:_}=c;if(f===Ie){Ht(h,b);return}if(f===Xs){E(c);return}const g=()=>{r(h),_&&!_.persisted&&_.afterLeave&&_.afterLeave()};if(c.shapeFlag&1&&_&&!_.persisted){const{leave:S,delayLeave:w}=_,x=()=>S(h,g);w?w(c.el,g,x):x()}else g()},Ht=(c,f)=>{let h;for(;c!==f;)h=m(c),r(c),c=h;r(f)},ps=(c,f,h)=>{const{bum:b,scope:_,job:g,subTree:S,um:w,m:x,a:y}=c;dr(x),dr(y),b&&vs(b),_.stop(),g&&(g.flags|=8,z(S,c,f,h)),w&&ke(w,f),ke(()=>{c.isUnmounted=!0},f)},it=(c,f,h,b=!1,_=!1,g=0)=>{for(let S=g;S<c.length;S++)z(c[S],f,h,b,_)},hs=c=>{if(c.shapeFlag&6)return hs(c.component.subTree);if(c.shapeFlag&128)return c.suspense.next();const f=m(c.anchor||c.el),h=f&&f[qi];return h?m(h):f};let rn=!1;const Br=(c,f,h)=>{c==null?f._vnode&&z(f._vnode,null,null,!0):L(f._vnode||null,c,f,null,null,null,h),f._vnode=c,rn||(rn=!0,jn(),Hn(),rn=!1)},Ut={p:L,um:z,m:V,r:Te,mt:ce,mc:ne,pc:H,pbc:Y,n:hs,o:e};return{render:Br,hydrate:void 0,createApp:fo(Br)}}function Qs({type:e,props:t},s){return s==="svg"&&e==="foreignObject"||s==="mathml"&&e==="annotation-xml"&&t&&t.encoding&&t.encoding.includes("html")?void 0:s}function ut({effect:e,job:t},s){s?(e.flags|=32,t.flags|=4):(e.flags&=-33,t.flags&=-5)}function wo(e,t){return(!e||e&&!e.pendingBranch)&&t&&!t.persisted}function ur(e,t,s=!1){const n=e.children,r=t.children;if(D(n)&&D(r))for(let i=0;i<n.length;i++){const o=n[i];let l=r[i];l.shapeFlag&1&&!l.dynamicChildren&&((l.patchFlag<=0||l.patchFlag===32)&&(l=r[i]=rt(r[i]),l.el=o.el),!s&&l.patchFlag!==-2&&ur(o,l)),l.type===as&&l.patchFlag!==-1&&(l.el=o.el),l.type===st&&!l.el&&(l.el=o.el)}}function So(e){const t=e.slice(),s=[0];let n,r,i,o,l;const a=e.length;for(n=0;n<a;n++){const d=e[n];if(d!==0){if(r=s[s.length-1],e[r]<d){t[n]=r,s.push(n);continue}for(i=0,o=s.length-1;i<o;)l=i+o>>1,e[s[l]]<d?i=l+1:o=l;d<e[s[i]]&&(i>0&&(t[n]=s[i-1]),s[i]=n)}}for(i=s.length,o=s[i-1];i-- >0;)s[i]=o,o=t[o];return s}function fr(e){const t=e.subTree.component;if(t)return t.asyncDep&&!t.asyncResolved?t:fr(t)}function dr(e){if(e)for(let t=0;t<e.length;t++)e[t].flags|=8}const ko=Symbol.for("v-scx"),Co=()=>ls(ko);function yt(e,t,s){return pr(e,t,s)}function pr(e,t,s=Z){const{immediate:n,deep:r,flush:i,once:o}=s,l=me({},s),a=t&&n||!t&&i!=="post";let d;if(jt){if(i==="sync"){const v=Co();d=v.__watcherHandles||(v.__watcherHandles=[])}else if(!a){const v=()=>{};return v.stop=$e,v.resume=$e,v.pause=$e,v}}const u=ve;l.call=(v,T,L)=>Ne(v,u,T,L);let p=!1;i==="post"?l.scheduler=v=>{ke(v,u&&u.suspense)}:i!=="sync"&&(p=!0,l.scheduler=(v,T)=>{T?v():Hs(v)}),l.augmentJob=v=>{t&&(v.flags|=4),p&&(v.flags|=2,u&&(v.id=u.uid,v.i=u))};const m=Mi(e,t,l);return jt&&(d?d.push(m):a&&m()),m}function Ao(e,t,s){const n=this.proxy,r=oe(e)?e.includes(".")?hr(n,e):()=>n[e]:e.bind(n,n);let i;N(t)?i=t:(i=t.handler,s=t);const o=Nt(this),l=pr(r,i.bind(n),s);return o(),l}function hr(e,t){const s=t.split(".");return()=>{let n=e;for(let r=0;r<s.length&&n;r++)n=n[s[r]];return n}}const To=(e,t)=>t==="modelValue"||t==="model-value"?e.modelModifiers:e[`${t}Modifiers`]||e[`${Xe(t)}Modifiers`]||e[`${ot(t)}Modifiers`];function Eo(e,t,...s){if(e.isUnmounted)return;const n=e.vnode.props||Z;let r=s;const i=t.startsWith("update:"),o=i&&To(n,t.slice(7));o&&(o.trim&&(r=s.map(u=>oe(u)?u.trim():u)),o.number&&(r=s.map(zr)));let l,a=n[l=ys(t)]||n[l=ys(Xe(t))];!a&&i&&(a=n[l=ys(ot(t))]),a&&Ne(a,e,6,r);const d=n[l+"Once"];if(d){if(!e.emitted)e.emitted={};else if(e.emitted[l])return;e.emitted[l]=!0,Ne(d,e,6,r)}}const Po=new WeakMap;function _r(e,t,s=!1){const n=s?Po:t.emitsCache,r=n.get(e);if(r!==void 0)return r;const i=e.emits;let o={},l=!1;if(!N(e)){const a=d=>{const u=_r(d,t,!0);u&&(l=!0,me(o,u))};!s&&t.mixins.length&&t.mixins.forEach(a),e.extends&&a(e.extends),e.mixins&&e.mixins.forEach(a)}return!i&&!l?(re(e)&&n.set(e,null),null):(D(i)?i.forEach(a=>o[a]=null):me(o,i),re(e)&&n.set(e,o),o)}function cs(e,t){return!e||!Vt(t)?!1:(t=t.slice(2).replace(/Once$/,""),W(e,t[0].toLowerCase()+t.slice(1))||W(e,ot(t))||W(e,t))}function bc(){}function gr(e){const{type:t,vnode:s,proxy:n,withProxy:r,propsOptions:[i],slots:o,attrs:l,emit:a,render:d,renderCache:u,props:p,data:m,setupState:v,ctx:T,inheritAttrs:L}=e,se=ns(e);let C,A;try{if(s.shapeFlag&4){const E=r||n,G=E;C=Ue(d.call(G,E,u,p,v,m,T)),A=l}else{const E=t;C=Ue(E.length>1?E(p,{attrs:l,slots:o,emit:a}):E(p,null)),A=t.props?l:Oo(l)}}catch(E){$t.length=0,ts(E,e,1),C=Ae(st)}let j=C;if(A&&L!==!1){const E=Object.keys(A),{shapeFlag:G}=j;E.length&&G&7&&(i&&E.some(gs)&&(A=Io(A,i)),j=vt(j,A,!1,!0))}return s.dirs&&(j=vt(j,null,!1,!0),j.dirs=j.dirs?j.dirs.concat(s.dirs):s.dirs),s.transition&&Us(j,s.transition),C=j,ns(se),C}const Oo=e=>{let t;for(const s in e)(s==="class"||s==="style"||Vt(s))&&((t||(t={}))[s]=e[s]);return t},Io=(e,t)=>{const s={};for(const n in e)(!gs(n)||!(n.slice(9)in t))&&(s[n]=e[n]);return s};function Mo(e,t,s){const{props:n,children:r,component:i}=e,{props:o,children:l,patchFlag:a}=t,d=i.emitsOptions;if(t.dirs||t.transition)return!0;if(s&&a>=0){if(a&1024)return!0;if(a&16)return n?mr(n,o,d):!!o;if(a&8){const u=t.dynamicProps;for(let p=0;p<u.length;p++){const m=u[p];if(o[m]!==n[m]&&!cs(d,m))return!0}}}else return(r||l)&&(!l||!l.$stable)?!0:n===o?!1:n?o?mr(n,o,d):!0:!!o;return!1}function mr(e,t,s){const n=Object.keys(t);if(n.length!==Object.keys(e).length)return!0;for(let r=0;r<n.length;r++){const i=n[r];if(t[i]!==e[i]&&!cs(s,i))return!0}return!1}function Ro({vnode:e,parent:t},s){for(;t;){const n=t.subTree;if(n.suspense&&n.suspense.activeBranch===e&&(n.el=e.el),n===e)(e=t.vnode).el=s,t=t.parent;else break}}const br=e=>e.__isSuspense;function Fo(e,t){t&&t.pendingBranch?D(e)?t.effects.push(...e):t.effects.push(e):Hi(e)}const Ie=Symbol.for("v-fgt"),as=Symbol.for("v-txt"),st=Symbol.for("v-cmt"),Xs=Symbol.for("v-stc"),$t=[];let Ce=null;function fe(e=!1){$t.push(Ce=e?null:[])}function $o(){$t.pop(),Ce=$t[$t.length-1]||null}let Lt=1;function yr(e,t=!1){Lt+=e,e<0&&Ce&&t&&(Ce.hasOnce=!0)}function vr(e){return e.dynamicChildren=Lt>0?Ce||ft:null,$o(),Lt>0&&Ce&&Ce.push(e),e}function ge(e,t,s,n,r,i){return vr(ie(e,t,s,n,r,i,!0))}function Lo(e,t,s,n,r){return vr(Ae(e,t,s,n,r,!0))}function xr(e){return e?e.__v_isVNode===!0:!1}function Dt(e,t){return e.type===t.type&&e.key===t.key}const wr=({key:e})=>e!=null?e:null,us=({ref:e,ref_key:t,ref_for:s})=>(typeof e=="number"&&(e=""+e),e!=null?oe(e)||ue(e)||N(e)?{i:He,r:e,k:t,f:!!s}:e:null);function ie(e,t=null,s=null,n=0,r=null,i=e===Ie?0:1,o=!1,l=!1){const a={__v_isVNode:!0,__v_skip:!0,type:e,props:t,key:t&&wr(t),ref:t&&us(t),scopeId:qn,slotScopeIds:null,children:s,component:null,suspense:null,ssContent:null,ssFallback:null,dirs:null,transition:null,el:null,anchor:null,target:null,targetStart:null,targetAnchor:null,staticCount:0,shapeFlag:i,patchFlag:n,dynamicProps:r,dynamicChildren:null,appContext:null,ctx:He};return l?(zs(a,s),i&128&&e.normalize(a)):s&&(a.shapeFlag|=oe(s)?8:16),Lt>0&&!o&&Ce&&(a.patchFlag>0||i&6)&&a.patchFlag!==32&&Ce.push(a),a}const Ae=Do;function Do(e,t=null,s=null,n=0,r=null,i=!1){if((!e||e===no)&&(e=st),xr(e)){const l=vt(e,t,!0);return s&&zs(l,s),Lt>0&&!i&&Ce&&(l.shapeFlag&6?Ce[Ce.indexOf(e)]=l:Ce.push(l)),l.patchFlag=-2,l}if(zo(e)&&(e=e.__vccOpts),t){t=No(t);let{class:l,style:a}=t;l&&!oe(l)&&(t.class=ws(l)),re(a)&&(Ls(a)&&!D(a)&&(a=me({},a)),t.style=xs(a))}const o=oe(e)?1:br(e)?128:Vi(e)?64:re(e)?4:N(e)?2:0;return ie(e,t,s,n,r,o,i,!0)}function No(e){return e?Ls(e)||nr(e)?me({},e):e:null}function vt(e,t,s=!1,n=!1){const{props:r,ref:i,patchFlag:o,children:l,transition:a}=e,d=t?Ho(r||{},t):r,u={__v_isVNode:!0,__v_skip:!0,type:e.type,props:d,key:d&&wr(d),ref:t&&t.ref?s&&i?D(i)?i.concat(us(t)):[i,us(t)]:us(t):i,scopeId:e.scopeId,slotScopeIds:e.slotScopeIds,children:l,target:e.target,targetStart:e.targetStart,targetAnchor:e.targetAnchor,staticCount:e.staticCount,shapeFlag:e.shapeFlag,patchFlag:t&&e.type!==Ie?o===-1?16:o|16:o,dynamicProps:e.dynamicProps,dynamicChildren:e.dynamicChildren,appContext:e.appContext,dirs:e.dirs,transition:a,component:e.component,suspense:e.suspense,ssContent:e.ssContent&&vt(e.ssContent),ssFallback:e.ssFallback&&vt(e.ssFallback),placeholder:e.placeholder,el:e.el,anchor:e.anchor,ctx:e.ctx,ce:e.ce};return a&&n&&Us(u,a.clone(u)),u}function jo(e=" ",t=0){return Ae(as,null,e,t)}function nt(e="",t=!1){return t?(fe(),Lo(st,null,e)):Ae(st,null,e)}function Ue(e){return e==null||typeof e=="boolean"?Ae(st):D(e)?Ae(Ie,null,e.slice()):xr(e)?rt(e):Ae(as,null,String(e))}function rt(e){return e.el===null&&e.patchFlag!==-1||e.memo?e:vt(e)}function zs(e,t){let s=0;const{shapeFlag:n}=e;if(t==null)t=null;else if(D(t))s=16;else if(typeof t=="object")if(n&65){const r=t.default;r&&(r._c&&(r._d=!1),zs(e,r()),r._c&&(r._d=!0));return}else{s=32;const r=t._;!r&&!nr(t)?t._ctx=He:r===3&&He&&(He.slots._===1?t._=1:(t._=2,e.patchFlag|=1024))}else N(t)?(t={default:t,_ctx:He},s=32):(t=String(t),n&64?(s=16,t=[jo(t)]):s=8);e.children=t,e.shapeFlag|=s}function Ho(...e){const t={};for(let s=0;s<e.length;s++){const n=e[s];for(const r in n)if(r==="class")t.class!==n.class&&(t.class=ws([t.class,n.class]));else if(r==="style")t.style=xs([t.style,n.style]);else if(Vt(r)){const i=t[r],o=n[r];o&&i!==o&&!(D(i)&&i.includes(o))&&(t[r]=i?[].concat(i,o):o)}else r!==""&&(t[r]=n[r])}return t}function qe(e,t,s,n=null){Ne(e,t,7,[s,n])}const Uo=er();let qo=0;function Vo(e,t,s){const n=e.type,r=(t?t.appContext:e.appContext)||Uo,i={uid:qo++,vnode:e,type:n,parent:t,appContext:r,root:null,next:null,subTree:null,effect:null,update:null,job:null,scope:new ri(!0),render:null,proxy:null,exposed:null,exposeProxy:null,withProxy:null,provides:t?t.provides:Object.create(r.provides),ids:t?t.ids:["",0,0],accessCache:null,renderCache:[],components:null,directives:null,propsOptions:ir(n,r),emitsOptions:_r(n,r),emit:null,emitted:null,propsDefaults:Z,inheritAttrs:n.inheritAttrs,ctx:Z,data:Z,props:Z,attrs:Z,slots:Z,refs:Z,setupState:Z,setupContext:null,suspense:s,suspenseId:s?s.pendingId:0,asyncDep:null,asyncResolved:!1,isMounted:!1,isUnmounted:!1,isDeactivated:!1,bc:null,c:null,bm:null,m:null,bu:null,u:null,um:null,bum:null,da:null,a:null,rtg:null,rtc:null,ec:null,sp:null};return i.ctx={_:i},i.root=t?t.root:i,i.emit=Eo.bind(null,i),e.ce&&e.ce(i),i}let ve=null;const Bo=()=>ve||He;let fs,Zs;{const e=Wt(),t=(s,n)=>{let r;return(r=e[s])||(r=e[s]=[]),r.push(n),i=>{r.length>1?r.forEach(o=>o(i)):r[0](i)}};fs=t("__VUE_INSTANCE_SETTERS__",s=>ve=s),Zs=t("__VUE_SSR_SETTERS__",s=>jt=s)}const Nt=e=>{const t=ve;return fs(e),e.scope.on(),()=>{e.scope.off(),fs(t)}},Sr=()=>{ve&&ve.scope.off(),fs(null)};function kr(e){return e.vnode.shapeFlag&4}let jt=!1;function Ko(e,t=!1,s=!1){t&&Zs(t);const{props:n,children:r}=e.vnode,i=kr(e);ho(e,n,i,t),bo(e,r,s||t);const o=i?Wo(e,t):void 0;return t&&Zs(!1),o}function Wo(e,t){const s=e.type;e.accessCache=Object.create(null),e.proxy=new Proxy(e.ctx,ro);const{setup:n}=s;if(n){Le();const r=e.setupContext=n.length>1?Jo(e):null,i=Nt(e),o=_t(n,e,0,[e.props,r]),l=cn(o);if(De(),i(),(l||e.sp)&&!Mt(e)&&Vn(e),l){if(o.then(Sr,Sr),t)return o.then(a=>{Cr(e,a)}).catch(a=>{ts(a,e,0)});e.asyncDep=o}else Cr(e,o)}else Ar(e)}function Cr(e,t,s){N(t)?e.type.__ssrInlineRender?e.ssrRender=t:e.render=t:re(t)&&(e.setupState=$n(t)),Ar(e)}function Ar(e,t,s){const n=e.type;e.render||(e.render=n.render||$e);{const r=Nt(e);Le();try{io(e)}finally{De(),r()}}}const Go={get(e,t){return _e(e,"get",""),e[t]}};function Jo(e){const t=s=>{e.exposed=s||{}};return{attrs:new Proxy(e.attrs,Go),slots:e.slots,emit:e.emit,expose:t}}function en(e){return e.exposed?e.exposeProxy||(e.exposeProxy=new Proxy($n(Ci(e.exposed)),{get(t,s){if(s in t)return t[s];if(s in Rt)return Rt[s](e)},has(t,s){return s in t||s in Rt}})):e.proxy}const Yo=/(?:^|[-_])\w/g,Qo=e=>e.replace(Yo,t=>t.toUpperCase()).replace(/[-_]/g,"");function Xo(e,t=!0){return N(e)?e.displayName||e.name:e.name||t&&e.__name}function Tr(e,t,s=!1){let n=Xo(t);if(!n&&t.__file){const r=t.__file.match(/([^/\\]+)\.\w+$/);r&&(n=r[1])}if(!n&&e&&e.parent){const r=i=>{for(const o in i)if(i[o]===t)return o};n=r(e.components||e.parent.type.components)||r(e.appContext.components)}return n?Qo(n):s?"App":"Anonymous"}function zo(e){return N(e)&&"__vccOpts"in e}const X=(e,t)=>Oi(e,t,jt),Zo="3.5.22";/**
* @vue/runtime-dom v3.5.22
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/let tn;const Er=typeof window!="undefined"&&window.trustedTypes;if(Er)try{tn=Er.createPolicy("vue",{createHTML:e=>e})}catch(e){}const Pr=tn?e=>tn.createHTML(e):e=>e,el="http://www.w3.org/2000/svg",tl="http://www.w3.org/1998/Math/MathML",Ye=typeof document!="undefined"?document:null,Or=Ye&&Ye.createElement("template"),sl={insert:(e,t,s)=>{t.insertBefore(e,s||null)},remove:e=>{const t=e.parentNode;t&&t.removeChild(e)},createElement:(e,t,s,n)=>{const r=t==="svg"?Ye.createElementNS(el,e):t==="mathml"?Ye.createElementNS(tl,e):s?Ye.createElement(e,{is:s}):Ye.createElement(e);return e==="select"&&n&&n.multiple!=null&&r.setAttribute("multiple",n.multiple),r},createText:e=>Ye.createTextNode(e),createComment:e=>Ye.createComment(e),setText:(e,t)=>{e.nodeValue=t},setElementText:(e,t)=>{e.textContent=t},parentNode:e=>e.parentNode,nextSibling:e=>e.nextSibling,querySelector:e=>Ye.querySelector(e),setScopeId(e,t){e.setAttribute(t,"")},insertStaticContent(e,t,s,n,r,i){const o=s?s.previousSibling:t.lastChild;if(r&&(r===i||r.nextSibling))for(;t.insertBefore(r.cloneNode(!0),s),!(r===i||!(r=r.nextSibling)););else{Or.innerHTML=Pr(n==="svg"?`<svg>${e}</svg>`:n==="mathml"?`<math>${e}</math>`:e);const l=Or.content;if(n==="svg"||n==="mathml"){const a=l.firstChild;for(;a.firstChild;)l.appendChild(a.firstChild);l.removeChild(a)}t.insertBefore(l,s)}return[o?o.nextSibling:t.firstChild,s?s.previousSibling:t.lastChild]}},nl=Symbol("_vtc");function rl(e,t,s){const n=e[nl];n&&(t=(t?[t,...n]:[...n]).join(" ")),t==null?e.removeAttribute("class"):s?e.setAttribute("class",t):e.className=t}const Ir=Symbol("_vod"),il=Symbol("_vsh"),ol=Symbol(""),ll=/(?:^|;)\s*display\s*:/;function cl(e,t,s){const n=e.style,r=oe(s);let i=!1;if(s&&!r){if(t)if(oe(t))for(const o of t.split(";")){const l=o.slice(0,o.indexOf(":")).trim();s[l]==null&&ds(n,l,"")}else for(const o in t)s[o]==null&&ds(n,o,"");for(const o in s)o==="display"&&(i=!0),ds(n,o,s[o])}else if(r){if(t!==s){const o=n[ol];o&&(s+=";"+o),n.cssText=s,i=ll.test(s)}}else t&&e.removeAttribute("style");Ir in e&&(e[Ir]=i?n.display:"",e[il]&&(n.display="none"))}const Mr=/\s*!important$/;function ds(e,t,s){if(D(s))s.forEach(n=>ds(e,t,n));else if(s==null&&(s=""),t.startsWith("--"))e.setProperty(t,s);else{const n=al(e,t);Mr.test(s)?e.setProperty(ot(n),s.replace(Mr,""),"important"):e[n]=s}}const Rr=["Webkit","Moz","ms"],sn={};function al(e,t){const s=sn[t];if(s)return s;let n=Xe(t);if(n!=="filter"&&n in e)return sn[t]=n;n=fn(n);for(let r=0;r<Rr.length;r++){const i=Rr[r]+n;if(i in e)return sn[t]=i}return t}const Fr="http://www.w3.org/1999/xlink";function $r(e,t,s,n,r,i=ni(t)){n&&t.startsWith("xlink:")?s==null?e.removeAttributeNS(Fr,t.slice(6,t.length)):e.setAttributeNS(Fr,t,s):s==null||i&&!hn(s)?e.removeAttribute(t):e.setAttribute(t,i?"":Qe(s)?String(s):s)}function Lr(e,t,s,n,r){if(t==="innerHTML"||t==="textContent"){s!=null&&(e[t]=t==="innerHTML"?Pr(s):s);return}const i=e.tagName;if(t==="value"&&i!=="PROGRESS"&&!i.includes("-")){const l=i==="OPTION"?e.getAttribute("value")||"":e.value,a=s==null?e.type==="checkbox"?"on":"":String(s);(l!==a||!("_value"in e))&&(e.value=a),s==null&&e.removeAttribute(t),e._value=s;return}let o=!1;if(s===""||s==null){const l=typeof e[t];l==="boolean"?s=hn(s):s==null&&l==="string"?(s="",o=!0):l==="number"&&(s=0,o=!0)}try{e[t]=s}catch(l){}o&&e.removeAttribute(r||t)}function ul(e,t,s,n){e.addEventListener(t,s,n)}function fl(e,t,s,n){e.removeEventListener(t,s,n)}const Dr=Symbol("_vei");function dl(e,t,s,n,r=null){const i=e[Dr]||(e[Dr]={}),o=i[t];if(n&&o)o.value=n;else{const[l,a]=pl(t);if(n){const d=i[t]=gl(n,r);ul(e,l,d,a)}else o&&(fl(e,l,o,a),i[t]=void 0)}}const Nr=/(?:Once|Passive|Capture)$/;function pl(e){let t;if(Nr.test(e)){t={};let n;for(;n=e.match(Nr);)e=e.slice(0,e.length-n[0].length),t[n[0].toLowerCase()]=!0}return[e[2]===":"?e.slice(3):ot(e.slice(2)),t]}let nn=0;const hl=Promise.resolve(),_l=()=>nn||(hl.then(()=>nn=0),nn=Date.now());function gl(e,t){const s=n=>{if(!n._vts)n._vts=Date.now();else if(n._vts<=s.attached)return;Ne(ml(n,s.value),t,5,[n])};return s.value=e,s.attached=_l(),s}function ml(e,t){if(D(t)){const s=e.stopImmediatePropagation;return e.stopImmediatePropagation=()=>{s.call(e),e._stopped=!0},t.map(n=>r=>!r._stopped&&n&&n(r))}else return t}const jr=e=>e.charCodeAt(0)===111&&e.charCodeAt(1)===110&&e.charCodeAt(2)>96&&e.charCodeAt(2)<123,bl=(e,t,s,n,r,i)=>{const o=r==="svg";t==="class"?rl(e,n,o):t==="style"?cl(e,s,n):Vt(t)?gs(t)||dl(e,t,s,n,i):(t[0]==="."?(t=t.slice(1),!0):t[0]==="^"?(t=t.slice(1),!1):yl(e,t,n,o))?(Lr(e,t,n),!e.tagName.includes("-")&&(t==="value"||t==="checked"||t==="selected")&&$r(e,t,n,o,i,t!=="value")):e._isVueCE&&(/[A-Z]/.test(t)||!oe(n))?Lr(e,Xe(t),n,i,t):(t==="true-value"?e._trueValue=n:t==="false-value"&&(e._falseValue=n),$r(e,t,n,o))};function yl(e,t,s,n){if(n)return!!(t==="innerHTML"||t==="textContent"||t in e&&jr(t)&&N(s));if(t==="spellcheck"||t==="draggable"||t==="translate"||t==="autocorrect"||t==="form"||t==="list"&&e.tagName==="INPUT"||t==="type"&&e.tagName==="TEXTAREA")return!1;if(t==="width"||t==="height"){const r=e.tagName;if(r==="IMG"||r==="VIDEO"||r==="CANVAS"||r==="SOURCE")return!1}return jr(t)&&oe(s)?!1:t in e}const vl=["ctrl","shift","alt","meta"],xl={stop:e=>e.stopPropagation(),prevent:e=>e.preventDefault(),self:e=>e.target!==e.currentTarget,ctrl:e=>!e.ctrlKey,shift:e=>!e.shiftKey,alt:e=>!e.altKey,meta:e=>!e.metaKey,left:e=>"button"in e&&e.button!==0,middle:e=>"button"in e&&e.button!==1,right:e=>"button"in e&&e.button!==2,exact:(e,t)=>vl.some(s=>e[`${s}Key`]&&!t.includes(s))},wl=(e,t)=>{const s=e._withMods||(e._withMods={}),n=t.join(".");return s[n]||(s[n]=(r,...i)=>{for(let o=0;o<t.length;o++){const l=xl[t[o]];if(l&&l(r,t))return}return e(r,...i)})},Sl=me({patchProp:bl},sl);let Hr;function kl(){return Hr||(Hr=vo(Sl))}const xt=(...e)=>{const t=kl().createApp(...e),{mount:s}=t;return t.mount=n=>{const r=Al(n);if(!r)return;const i=t._component;!N(i)&&!i.render&&!i.template&&(i.template=r.innerHTML),r.nodeType===1&&(r.textContent="");const o=s(r,!1,Cl(r));return r instanceof Element&&(r.removeAttribute("v-cloak"),r.setAttribute("data-v-app","")),o},t};function Cl(e){if(e instanceof SVGElement)return"svg";if(typeof MathMLElement=="function"&&e instanceof MathMLElement)return"mathml"}function Al(e){return oe(e)?document.querySelector(e):e}const Tl={class:"nxp-ec-landing__hero"},El={class:"nxp-ec-landing__hero-copy"},Pl={key:0,class:"nxp-ec-landing__eyebrow"},Ol={class:"nxp-ec-landing__title"},Il={key:1,class:"nxp-ec-landing__subtitle"},Ml={class:"nxp-ec-landing__actions"},Rl=["href"],Fl={class:"sr-only",for:"nxp-ec-landing-search-input"},$l=["value","placeholder"],Ll={type:"submit",class:"nxp-ec-btn nxp-ec-btn--ghost"},Dl={__name:"LandingHero",props:{hero:{type:Object,default:()=>({})},cta:{type:Object,default:()=>({label:"Shop Now",link:"#"})},labels:{type:Object,default:()=>({search_label:"Search the catalogue",search_button:"Search"})},term:{type:String,default:""},searchPlaceholder:{type:String,default:""}},emits:["update:term","submit"],setup(e,{emit:t}){const s=t,n=i=>{s("update:term",i)},r=()=>{s("submit")};return(i,o)=>(fe(),ge("header",Tl,[ie("div",El,[e.hero.eyebrow?(fe(),ge("p",Pl,he(e.hero.eyebrow),1)):nt("",!0),ie("h1",Ol,he(e.hero.title),1),e.hero.subtitle?(fe(),ge("p",Il,he(e.hero.subtitle),1)):nt("",!0),ie("div",Ml,[ie("a",{class:"nxp-ec-btn nxp-ec-btn--primary",href:e.cta.link},he(e.cta.label),9,Rl)])]),ie("form",{class:"nxp-ec-landing__search",onSubmit:wl(r,["prevent"])},[ie("label",Fl,he(e.labels.search_label),1),ie("input",{id:"nxp-ec-landing-search-input",type:"search",value:e.term,onInput:o[0]||(o[0]=l=>n(l.target.value)),placeholder:e.searchPlaceholder},null,40,$l),ie("button",Ll,he(e.labels.search_button),1)],32)]))}},Nl=["aria-label"],jl=["href"],Hl={class:"nxp-ec-landing__category-title"},Ul={__name:"LandingCategories",props:{categories:{type:Array,default:()=>[]},ariaLabel:{type:String,default:""}},setup(e){return(t,s)=>e.categories.length?(fe(),ge("section",{key:0,class:"nxp-ec-landing__categories","aria-label":e.ariaLabel},[(fe(!0),ge(Ie,null,qs(e.categories,n=>(fe(),ge("a",{key:n.id||n.slug||n.title,class:"nxp-ec-landing__category",href:n.link},[ie("span",Hl,he(n.title),1)],8,jl))),128))],8,Nl)):nt("",!0)}},ql={class:"nxp-ec-landing__section-header"},Vl={class:"nxp-ec-landing__section-title"},Bl=["href"],Kl={class:"nxp-ec-landing__grid"},Wl=["href","aria-label"],Gl=["src","alt"],Jl={class:"nxp-ec-landing__card-body"},Yl={class:"nxp-ec-landing__card-title"},Ql=["href"],Xl={key:0,class:"nxp-ec-landing__card-intro"},zl={key:1,class:"nxp-ec-landing__card-price"},Zl={class:"nxp-ec-landing__card-actions"},ec=["href"],tc=["aria-label","disabled","onClick"],sc={class:"nxp-ec-sr-only"},nc={__name:"LandingSections",props:{sections:{type:Array,default:()=>[]},labels:{type:Object,default:()=>({view_all:"View all",view_product:"View product",add_to_cart:"Add to cart"})},searchAction:{type:String,default:""},cart:{type:Object,default:()=>({})}},setup(e){const t=e,s=Pe({}),n=l=>l.id||l.slug||l.title||Math.random().toString(36),r=l=>{var a,d;return!(!((d=(a=t.cart)==null?void 0:a.endpoints)!=null&&d.add)||!l||!l.primary_variant_id)},i=l=>(s[l]||(s[l]={loading:!1}),s[l]),o=async l=>{var u;const a=n(l),d=i(a);if(!r(l)){window.location.href=l.link||t.searchAction;return}d.loading=!0;try{const p=new FormData;t.cart.token&&p.append(t.cart.token,"1"),p.append("product_id",String(l.id||"")),p.append("variant_id",String(l.primary_variant_id)),p.append("qty","1");let m=null;const v=await fetch(t.cart.endpoints.add,{method:"POST",body:p,headers:{Accept:"application/json"}});try{m=await v.json()}catch(L){}if(!v.ok||!m||m.success===!1)throw new Error(m&&m.message||t.labels.add_to_cart);const T=((u=m.data)==null?void 0:u.cart)||null;T&&window.dispatchEvent(new CustomEvent("nxp-cart:updated",{detail:T}))}catch(p){console.error(p)}finally{d.loading=!1}};return(l,a)=>(fe(!0),ge(Ie,null,qs(e.sections,d=>(fe(),ge("section",{key:d.key,class:"nxp-ec-landing__section"},[ie("header",ql,[ie("h2",Vl,he(d.title),1),ie("a",{class:"nxp-ec-landing__section-link",href:e.searchAction},he(e.labels.view_all),9,Bl)]),ie("div",Kl,[(fe(!0),ge(Ie,null,qs(d.items,u=>{var p;return fe(),ge("article",{key:u.id||u.slug||u.title,class:"nxp-ec-landing__card"},[u.images&&u.images.length?(fe(),ge("a",{key:0,class:"nxp-ec-landing__card-media",href:u.link,"aria-label":`${e.labels.view_product}: ${u.title}`},[ie("img",{src:u.images[0],alt:u.title,loading:"lazy"},null,8,Gl)],8,Wl)):nt("",!0),ie("div",Jl,[ie("h3",Yl,[ie("a",{href:u.link},he(u.title),9,Ql)]),u.short_desc?(fe(),ge("p",Xl,he(u.short_desc),1)):nt("",!0),u.price_label?(fe(),ge("p",zl,he(u.price_label),1)):nt("",!0),ie("div",Zl,[ie("a",{class:"nxp-ec-btn nxp-ec-btn--ghost",href:u.link},he(e.labels.view_product),9,ec),r(u)?(fe(),ge("button",{key:0,type:"button",class:"nxp-ec-btn nxp-ec-btn--icon","aria-label":`${e.labels.add_to_cart}: ${u.title}`,disabled:(p=s[n(u)])==null?void 0:p.loading,onClick:m=>o(u)},[a[0]||(a[0]=ie("span",{"aria-hidden":"true"},"+",-1)),ie("span",sc,he(e.labels.add_to_cart),1)],8,tc)):nt("",!0)])])])}),128))])]))),128))}},rc={key:0,class:"nxp-ec-landing__trust"},ic={class:"nxp-ec-landing__trust-text"},oc={__name:"LandingTrust",props:{trust:{type:Object,default:()=>({text:""})}},setup(e){return(t,s)=>e.trust.text?(fe(),ge("aside",rc,[ie("p",ic,he(e.trust.text),1)])):nt("",!0)}},lc=12;function cc(e,t=lc){return X(()=>ac(Ns(e)).map(n=>({key:n.key||n.title||`section-${n.__index}`,title:n.title||"",items:(n.items||[]).slice(0,t)})))}function ac(e){return Array.isArray(e)?e.filter(t=>t&&typeof t=="object"&&Array.isArray(t.items)&&t.items.length).map((t,s)=>({...t,__index:s})):[]}const uc={class:"nxp-ec-landing__inner"},Ur="index.php?option=com_nxpeasycart&view=category",fc={__name:"LandingApp",props:{hero:{type:Object,default:()=>({})},cta:{type:Object,default:()=>({label:"Shop Best Sellers",link:"index.php?option=com_nxpeasycart&view=category"})},categories:{type:Array,default:()=>[]},sections:{type:Array,default:()=>[]},labels:{type:Object,default:()=>({})},cart:{type:Object,default:()=>({})},trust:{type:Object,default:()=>({text:""})},searchAction:{type:String,default:"index.php?option=com_nxpeasycart&view=category"},searchPlaceholder:{type:String,default:""}},setup(e,{expose:t}){const s=e,n=Ds(""),r=X(()=>{var C,A,j;return{eyebrow:((C=s.hero)==null?void 0:C.eyebrow)||"",title:((A=s.hero)==null?void 0:A.title)||"Shop",subtitle:((j=s.hero)==null?void 0:j.subtitle)||""}}),i=X(()=>{var C,A;return{label:((C=s.cta)==null?void 0:C.label)||"Shop Best Sellers",link:((A=s.cta)==null?void 0:A.link)||s.searchAction||Ur}}),o=X(()=>{var C,A,j,E,G,O,U,ne;return{search_label:((C=s.labels)==null?void 0:C.search_label)||"Search the catalogue",search_button:((A=s.labels)==null?void 0:A.search_button)||"Search",view_all:((j=s.labels)==null?void 0:j.view_all)||"View all",view_product:((E=s.labels)==null?void 0:E.view_product)||"View product",add_to_cart:((G=s.labels)==null?void 0:G.add_to_cart)||"Add to cart",added:((O=s.labels)==null?void 0:O.added)||"Added to cart",view_cart:((U=s.labels)==null?void 0:U.view_cart)||"View cart",categories_aria:((ne=s.labels)==null?void 0:ne.categories_aria)||"Browse categories"}}),l=X(()=>{var C;return(C=s.categories)!=null?C:[]}),a=X(()=>{var C;return(C=s.sections)!=null?C:[]}),d=cc(a),u=X(()=>s.trust&&typeof s.trust.text=="string"?s.trust:{text:""}),p=X(()=>s.searchAction||Ur),m=X(()=>s.searchPlaceholder||"Search for shoes, laptops, gifts…"),v=X(()=>{var C;return(C=s.cart)!=null?C:{}}),T=C=>{n.value=C},L=C=>{const A=p.value;try{const j=new URL(A,window.location.origin);C?j.searchParams.set("q",C):j.searchParams.delete("q"),window.location.href=j.toString()}catch(j){if(C){const E=A.includes("?")?"&":"?";window.location.href=`${A}${E}q=${encodeURIComponent(C)}`;return}window.location.href=A}},se=()=>{L(n.value.trim())};return t({submitSearch:se}),(C,A)=>(fe(),ge("div",uc,[Ae(Dl,{hero:r.value,cta:i.value,labels:o.value,term:n.value,"search-placeholder":m.value,"onUpdate:term":T,onSubmit:se},null,8,["hero","cta","labels","term","search-placeholder"]),Ae(Ul,{categories:l.value,"aria-label":o.value.categories_aria},null,8,["categories","aria-label"]),Ae(nc,{sections:Ns(d),labels:o.value,"search-action":p.value,cart:v.value},null,8,["sections","labels","search-action","cart"]),Ae(oc,{trust:u.value},null,8,["trust"])]))}};function Fe(e,t={}){if(!e)return t;try{return JSON.parse(e)}catch(s){return console.warn("[NXP Easy Cart] Failed to parse island payload",s),t}}const dc="index.php?option=com_nxpeasycart&view=category",pc="Search for shoes, laptops, gifts…";function hc(e){var v,T;const t=Fe(e.dataset.nxpLanding,{}),s=t.hero||{},n=t.search||{},r=t.labels||{},i=t.trust||{},o=Array.isArray(t.sections)?t.sections:[],l=Array.isArray(t.categories)?t.categories:[],a=n.action||dc,d={eyebrow:s.eyebrow||"",title:s.title||"Shop",subtitle:s.subtitle||""},u=t.cart||{},p={label:((v=s==null?void 0:s.cta)==null?void 0:v.label)||"Shop Best Sellers",link:((T=s==null?void 0:s.cta)==null?void 0:T.link)||a},m={search_label:r.search_label||"Search the catalogue",search_button:r.search_button||"Search",view_all:r.view_all||"View all",view_product:r.view_product||"View product",categories_aria:r.categories_aria||"Browse categories"};e.innerHTML="",xt(fc,{hero:d,cta:p,categories:l,sections:o,labels:m,trust:typeof i.text=="string"?i:{text:""},searchAction:a,searchPlaceholder:n.placeholder||pc,cart:u}).mount(e)}const wt=(e,t)=>{const s=(e||0)/100;try{return new Intl.NumberFormat(void 0,{style:"currency",currency:t||"USD",minimumFractionDigits:2}).format(s)}catch(n){return`${t?`${t} `:""}${s.toFixed(2)}`}},qr={product:e=>{var v,T,L,se,C,A,j,E,G,O,U,ne,de;const t=Fe(e.dataset.nxpProduct,{}),s=t.product||{},r=(Array.isArray(t.variants)?t.variants:[]).map(Y=>({...Y,id:Number(Y.id||0),stock:Y.stock===null||Y.stock===void 0?null:Number(Y.stock)})).filter(Y=>Number.isFinite(Y.id)&&Y.id>0),i={add_to_cart:((v=t.labels)==null?void 0:v.add_to_cart)||"Add to cart",select_variant:((T=t.labels)==null?void 0:T.select_variant)||"Select a variant",out_of_stock:((L=t.labels)==null?void 0:L.out_of_stock)||"Out of stock",added:((se=t.labels)==null?void 0:se.added)||"Added to cart",view_cart:((C=t.labels)==null?void 0:C.view_cart)||"View cart",qty_label:((A=t.labels)==null?void 0:A.qty_label)||"Quantity",error_generic:((j=t.labels)==null?void 0:j.error_generic)||"We couldn't add this item to your cart. Please try again.",variants_heading:((E=t.labels)==null?void 0:E.variants_heading)||"Variants",variant_sku:((G=t.labels)==null?void 0:G.variant_sku)||"SKU",variant_price:((O=t.labels)==null?void 0:O.variant_price)||"Price",variant_stock:((U=t.labels)==null?void 0:U.variant_stock)||"Stock",variant_options:((ne=t.labels)==null?void 0:ne.variant_options)||"Options",variant_none:((de=t.labels)==null?void 0:de.variant_none)||"—"},o=t.endpoints||{},l=t.links||{},a=t.token||"",d=Array.isArray(s.images)?s.images:[],u=d.length?d[0]:"",p=t.primary_alt||s.title||i.add_to_cart;e.innerHTML="",xt({template:`
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
    `,setup(){const Y=`nxp-ec-variant-${s.id||"0"}`,le=`nxp-ec-qty-${s.id||"0"}`,I=Pe({variantId:r.length===1?r[0].id:null,qty:1,loading:!1,success:!1,successMessage:"",error:""}),Q=X(()=>r.length?I.variantId?r.find(F=>F.id===I.variantId)||null:r.length===1?r[0]:null:null),ce=X(()=>{const F=Q.value;if(!F||F.stock===null||F.stock===void 0||!Number.isFinite(F.stock))return;const V=Number(F.stock);if(!(!Number.isFinite(V)||V<=0))return V}),Me=F=>{let V=Number(F);(!Number.isFinite(V)||V<1)&&(V=1);const z=ce.value;return Number.isFinite(z)&&(V=Math.min(V,z)),V};yt(()=>I.qty,F=>{const V=Me(F);V!==F&&(I.qty=V)}),yt(()=>I.variantId,()=>{I.error="",I.success=!1,I.successMessage="";const F=Me(I.qty);F!==I.qty&&(I.qty=F)});const P=X(()=>{var F;return Q.value&&Q.value.price_label?Q.value.price_label:((F=s.price)==null?void 0:F.label)||""}),q=X(()=>{const F=Q.value;return!F||F.stock===null||F.stock===void 0?!1:Number(F.stock)<=0}),H=X(()=>!!(I.loading||!r.length||!Q.value||q.value));return{product:s,variants:r,labels:i,links:l,primaryImage:u,primaryAlt:p,state:I,add:async()=>{var V;if(I.error="",I.success=!1,I.successMessage="",!o.add){I.error=i.error_generic;return}const F=Q.value;if(r.length&&!F){I.error=i.select_variant;return}if(q.value){I.error=i.out_of_stock;return}I.loading=!0;try{const z=new FormData;a&&z.append(a,"1"),z.append("product_id",String(s.id||"")),z.append("qty",String(Me(I.qty))),F&&z.append("variant_id",String(F.id));let Te=null;const Ht=await fetch(o.add,{method:"POST",body:z,headers:{Accept:"application/json"}});try{Te=await Ht.json()}catch(it){}if(!Ht.ok||!Te||Te.success===!1){const it=Te&&Te.message||i.error_generic;throw new Error(it)}const ps=((V=Te.data)==null?void 0:V.cart)||null;I.success=!0,I.successMessage=Te.message||i.added,ps&&window.dispatchEvent(new CustomEvent("nxp-cart:updated",{detail:ps}))}catch(z){I.error=z&&z.message||i.error_generic}finally{I.loading=!1}},displayPrice:P,isDisabled:H,isOutOfStock:q,maxQty:ce,variantSelectId:Y,qtyInputId:le}}}).mount(e)},category:e=>{const t=Fe(e.dataset.nxpCategory,{}),s=Fe(e.dataset.nxpProducts,[]),n=Fe(e.dataset.nxpCategories,[]),r=Fe(e.dataset.nxpLabels,{}),i=Fe(e.dataset.nxpLinks,{}),o=(e.dataset.nxpSearch||"").trim(),l=O=>{if(O==null||O==="")return null;const U=Number.parseInt(O,10);return Number.isFinite(U)?U:null},a={filters:r.filters||"Categories",filter_all:r.filter_all||"All",empty:r.empty||"No products found in this category yet.",view_product:r.view_product||"View product",search_placeholder:r.search_placeholder||"Search products",search_label:r.search_label||r.search_placeholder||"Search products",add_to_cart:r.add_to_cart||"Add to cart",added:r.added||"Added to cart",view_cart:r.view_cart||"View cart",out_of_stock:r.out_of_stock||"Out of stock",error_generic:r.error_generic||"We couldn't add this item to your cart. Please try again."},d={all:typeof i.all=="string"&&i.all!==""?i.all:"index.php?option=com_nxpeasycart&view=category",search:typeof i.search=="string"&&i.search!==""?i.search:"index.php?option=com_nxpeasycart&view=category"},u=Fe(e.dataset.nxpCart,{}),p=u.token||"",m=u.endpoints||{},v=u.links||{},T=Array.isArray(s)?s:[],L=Array.isArray(n)?n:[],se=t&&typeof t.slug=="string"?t.slug:"",C=`nxp-ec-category-search-${(t==null?void 0:t.id)||"all"}`,A=T.filter(O=>O&&typeof O=="object").map(O=>{const U=O.price&&typeof O.price=="object"?O.price:{},ne=l(U.min_cents),de=l(U.max_cents),Y=typeof U.currency=="string"&&U.currency!==""?U.currency:"USD";let le=typeof O.price_label=="string"?O.price_label:"";!le&&ne!==null&&de!==null&&(ne===de?le=wt(ne,Y):le=`${wt(ne,Y)} - ${wt(de,Y)}`);const I=Array.isArray(O.images)?O.images.filter(ce=>typeof ce=="string"&&ce.trim()!=="").map(ce=>ce.trim()):[],Q=Number.parseInt(O.primary_variant_id,10);return{...O,title:typeof O.title=="string"?O.title:"",short_desc:typeof O.short_desc=="string"?O.short_desc:"",link:typeof O.link=="string"&&O.link!==""?O.link:"#",images:I,price:{currency:Y,min_cents:ne,max_cents:de},price_label:le,primary_variant_id:Number.isFinite(Q)?Q:null}}),j=L.filter(O=>O&&typeof O=="object").map((O,U)=>({...O,title:typeof O.title=="string"&&O.title!==""?O.title:U===0?a.filter_all:"",slug:typeof O.slug=="string"?O.slug:"",link:typeof O.link=="string"&&O.link!==""?O.link:d.all})),E=O=>{if(!(typeof window=="undefined"||!window.history||typeof window.history.replaceState!="function"))try{const U=new URL(window.location.href);O?U.searchParams.set("q",O):U.searchParams.delete("q"),window.history.replaceState({},"",U.toString())}catch(U){}};e.innerHTML="",xt({template:`
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
            <a
              v-if="product.images.length"
              class="nxp-ec-product-card__media"
              :href="product.link"
              :aria-label="labels.view_product + ': ' + product.title"
            >
              <img :src="product.images[0]" :alt="product.title" loading="lazy" />
            </a>
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
              <div class="nxp-ec-product-card__actions">
                <a class="nxp-ec-btn nxp-ec-btn--ghost" :href="product.link">
                  {{ labels.view_product }}
                </a>
                <button
                  v-if="canQuickAdd(product)"
                  type="button"
                  class="nxp-ec-btn nxp-ec-btn--icon"
                  :aria-label="labels.add_to_cart + ': ' + product.title"
                  :disabled="quickState[keyFor(product)]?.loading"
                  @click="quickAdd(product)"
                >
                  <span aria-hidden="true">+</span>
                  <span class="nxp-ec-sr-only">{{ labels.add_to_cart }}</span>
                </button>
              </div>
              <p
                v-if="quickState[keyFor(product)]?.error"
                class="nxp-ec-product-card__hint nxp-ec-product-card__hint--error"
              >
                {{ quickState[keyFor(product)].error }}
              </p>
              <p
                v-else-if="quickState[keyFor(product)]?.success"
                class="nxp-ec-product-card__hint"
              >
                {{ labels.added }}
                <template v-if="cartLinks.cart">
                  · <a :href="cartLinks.cart">{{ labels.view_cart }}</a>
                </template>
              </p>
            </div>
          </article>
        </div>
      </div>
    `,setup(){const O=t&&typeof t.title=="string"&&t.title||"Products",U=Ds(o),ne=X(()=>{const P=U.value.trim().toLowerCase();return P?A.filter(q=>`${q.title} ${q.short_desc||""}`.toLowerCase().includes(P)):A});yt(U,(P,q)=>{const H=P.trim();q!==void 0&&q.trim()===H||E(H)},{immediate:!0});const de=()=>{E(U.value.trim())},Y=P=>(typeof P.slug=="string"?P.slug:"")===se,le=Pe({}),I=P=>P.id||P.slug||P.title||"product",Q=P=>(le[P]||(le[P]={loading:!1,error:"",success:!1}),le[P]),ce=P=>!!(m.add&&P&&P.primary_variant_id);return{title:O,search:U,searchId:C,filteredProducts:ne,submitSearch:de,labels:a,filters:j,links:d,isActive:Y,quickAdd:async P=>{var pe;const q=I(P),H=Q(q);if(!ce(P)){window.location.href=P.link||d.search;return}H.loading=!0,H.error="",H.success=!1;try{const F=new FormData;p&&F.append(p,"1"),F.append("product_id",String(P.id||"")),F.append("variant_id",String(P.primary_variant_id)),F.append("qty","1");let V=null;const z=await fetch(m.add,{method:"POST",body:F,headers:{Accept:"application/json"}});try{V=await z.json()}catch(Ht){}if(!z.ok||!V||V.success===!1)throw new Error(V&&V.message||a.error_generic);const Te=((pe=V.data)==null?void 0:pe.cart)||null;Te&&window.dispatchEvent(new CustomEvent("nxp-cart:updated",{detail:Te})),H.success=!0}catch(F){H.error=F&&F.message||a.error_generic}finally{H.loading=!1}},canQuickAdd:ce,quickState:le,keyFor:I,cartLinks:v}}}).mount(e)},landing:hc,cart:e=>{const t=Fe(e.dataset.nxpCart,{items:[],summary:{}});e.innerHTML="",xt({template:`
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
    `,setup(){var d,u,p;const n=Pe(t.items||[]),r=((d=t.summary)==null?void 0:d.currency)||"USD",i=Pe({subtotal_cents:((u=t.summary)==null?void 0:u.subtotal_cents)||0,total_cents:((p=t.summary)==null?void 0:p.total_cents)||0}),o=()=>{const m=n.reduce((v,T)=>v+(T.total_cents||0),0);i.subtotal_cents=m,i.total_cents=m};return{items:n,summary:i,remove:m=>{const v=n.indexOf(m);v>=0&&(n.splice(v,1),o())},updateQty:(m,v)=>{const T=Math.max(1,parseInt(v,10)||1);m.qty=T,m.total_cents=T*(m.unit_price_cents||0),o()},format:m=>wt(m,r)}}}).mount(e)},"cart-summary":e=>{const t=Fe(e.dataset.nxpCartSummary,{}),s=t.labels||{},n=t.links||{};e.innerHTML="",xt({template:`
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
    `,setup(){const i=Pe({count:Number(t.count||0),total_cents:Number(t.total_cents||0),currency:t.currency||"USD"}),o=X(()=>i.count===1?s.items_single||"1 item":(s.items_plural||"%d items").replace("%d",i.count)),l=X(()=>wt(i.total_cents,i.currency||"USD")),a=d=>{var m,v;if(!d)return;const u=Array.isArray(d.items)?d.items:[];let p=0;u.forEach(T=>{p+=Number(T.qty||0)}),i.count=p,i.total_cents=Number(((m=d.summary)==null?void 0:m.total_cents)||i.total_cents),i.currency=((v=d.summary)==null?void 0:v.currency)||i.currency||"USD"};return window.addEventListener("nxp-cart:updated",d=>{a(d.detail)}),{state:i,labels:s,links:n,countLabel:o,totalLabel:l}}}).mount(e)},checkout:e=>{const t=Fe(e.dataset.nxpCheckout,{}),s=t.cart||{items:[],summary:{}},n=t.shipping_rules||[];t.tax_rates;const r=t.settings||{},i=t.payments||{},o=t.endpoints||{},l=t.token||"";e.innerHTML="",xt({template:`
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
    `,setup(){var U,ne,de,Y,le;const d=Pe((s.items||[]).map(I=>({...I}))),u=((U=s.summary)==null?void 0:U.currency)||r.base_currency||"USD",p=n.map((I,Q)=>({...I,price_cents:I.price_cents||0,default:Q===0})),m=(I,Q=[])=>Q.every(ce=>{var P;const Me=(P=I[ce])!=null?P:"";return String(Me).trim()!==""}),v=[];m((ne=i.stripe)!=null?ne:{},["publishable_key","secret_key"])&&v.push({id:"stripe",label:"Card (Stripe)"}),m((de=i.paypal)!=null?de:{},["client_id","client_secret"])&&v.push({id:"paypal",label:"PayPal"});const T=v,L=Ds(((Y=T[0])==null?void 0:Y.id)||""),se=T.length>0&&!!o.payment,C=Pe({email:"",billing:{first_name:"",last_name:"",address_line1:"",city:"",postcode:"",country:""},shipping_rule_id:((le=p[0])==null?void 0:le.id)||null}),A=Pe({loading:!1,error:"",success:!1,orderNumber:"",orderUrl:"index.php?option=com_nxpeasycart&view=order"}),j=X(()=>d.reduce((I,Q)=>I+(Q.total_cents||0),0)),E=X(()=>{const I=p.find(Q=>String(Q.id)===String(C.shipping_rule_id));return I?I.price_cents:0}),G=X(()=>j.value+E.value);return{model:C,cartItems:d,shippingRules:p,subtotal:j,selectedShippingCost:E,total:G,submit:async()=>{var ce,Me;if(A.error="",d.length===0){A.error="Your cart is empty.";return}A.loading=!0;const I=L.value||((ce=T[0])==null?void 0:ce.id)||"",Q={email:C.email,billing:C.billing,shipping_rule_id:C.shipping_rule_id,items:d.map(P=>({sku:P.sku,qty:P.qty,product_id:P.product_id,variant_id:P.variant_id,unit_price_cents:P.unit_price_cents,total_cents:P.total_cents,currency:u,title:P.title})),currency:u,totals:{subtotal_cents:j.value,shipping_cents:E.value,total_cents:G.value},gateway:I};try{if(se&&I){const pe=await fetch(o.payment,{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-Token":l,"X-Requested-With":"XMLHttpRequest"},body:JSON.stringify(Q),credentials:"same-origin"});if(!pe.ok){const z=`Checkout failed (${pe.status})`;throw new Error(z)}const F=await pe.json(),V=(Me=F==null?void 0:F.checkout)==null?void 0:Me.url;if(!V)throw new Error("Missing checkout URL from gateway.");window.location.href=V;return}if(!o.checkout)throw new Error("Checkout endpoint unavailable.");const P=await fetch(o.checkout,{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-Token":l,"X-Requested-With":"XMLHttpRequest"},body:JSON.stringify(Q),credentials:"same-origin"});if(!P.ok){const pe=`Checkout failed (${P.status})`;throw new Error(pe)}const q=await P.json(),H=(q==null?void 0:q.order)||{};A.success=!0,A.orderNumber=H.order_no||"",A.orderUrl=`index.php?option=com_nxpeasycart&view=order&no=${encodeURIComponent(A.orderNumber)}`}catch(P){A.error=P.message||"Unable to complete checkout right now."}finally{A.loading=!1}},loading:X(()=>A.loading),error:X(()=>A.error),success:X(()=>A.success),orderNumber:X(()=>A.orderNumber),orderUrl:X(()=>A.orderUrl),formatMoney:I=>wt(I,u),gateways:T,selectedGateway:L}}}).mount(e)}},Vr=()=>{document.querySelectorAll("[data-nxp-island]").forEach(e=>{const t=e.dataset.nxpIsland;!t||!qr[t]||qr[t](e)})};document.readyState==="loading"?document.addEventListener("DOMContentLoaded",Vr):Vr()})();
