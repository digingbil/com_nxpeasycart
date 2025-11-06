(function(){"use strict";/**
* @vue/shared v3.5.22
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/function is(e){const t=Object.create(null);for(const s of e.split(","))t[s]=1;return s=>s in t}const G={},pt=[],Te=()=>{},Qs=()=>!1,Ft=e=>e.charCodeAt(0)===111&&e.charCodeAt(1)===110&&(e.charCodeAt(2)>122||e.charCodeAt(2)<97),os=e=>e.startsWith("onUpdate:"),le=Object.assign,ls=(e,t)=>{const s=e.indexOf(t);s>-1&&e.splice(s,1)},kr=Object.prototype.hasOwnProperty,V=(e,t)=>kr.call(e,t),N=Array.isArray,ht=e=>Nt(e)==="[object Map]",Rr=e=>Nt(e)==="[object Set]",F=e=>typeof e=="function",te=e=>typeof e=="string",rt=e=>typeof e=="symbol",ee=e=>e!==null&&typeof e=="object",Xs=e=>(ee(e)||F(e))&&F(e.then)&&F(e.catch),Fr=Object.prototype.toString,Nt=e=>Fr.call(e),Nr=e=>Nt(e).slice(8,-1),Dr=e=>Nt(e)==="[object Object]",cs=e=>te(e)&&e!=="NaN"&&e[0]!=="-"&&""+parseInt(e,10)===e,gt=is(",key,ref,ref_for,ref_key,onVnodeBeforeMount,onVnodeMounted,onVnodeBeforeUpdate,onVnodeUpdated,onVnodeBeforeUnmount,onVnodeUnmounted"),Dt=e=>{const t=Object.create(null);return s=>t[s]||(t[s]=e(s))},Lr=/-\w/g,Ve=Dt(e=>e.replace(Lr,t=>t.slice(1).toUpperCase())),Hr=/\B([A-Z])/g,Qe=Dt(e=>e.replace(Hr,"-$1").toLowerCase()),zs=Dt(e=>e.charAt(0).toUpperCase()+e.slice(1)),as=Dt(e=>e?`on${zs(e)}`:""),We=(e,t)=>!Object.is(e,t),us=(e,...t)=>{for(let s=0;s<e.length;s++)e[s](...t)},Zs=(e,t,s,n=!1)=>{Object.defineProperty(e,t,{configurable:!0,enumerable:!1,writable:n,value:s})},$r=e=>{const t=parseFloat(e);return isNaN(t)?e:t};let en;const Lt=()=>en||(en=typeof globalThis!="undefined"?globalThis:typeof self!="undefined"?self:typeof window!="undefined"?window:typeof global!="undefined"?global:{});function fs(e){if(N(e)){const t={};for(let s=0;s<e.length;s++){const n=e[s],r=te(n)?Vr(n):fs(n);if(r)for(const i in r)t[i]=r[i]}return t}else if(te(e)||ee(e))return e}const jr=/;(?![^(]*\))/g,Ur=/:([^]+)/,qr=/\/\*[^]*?\*\//g;function Vr(e){const t={};return e.replace(qr,"").split(jr).forEach(s=>{if(s){const n=s.split(Ur);n.length>1&&(t[n[0].trim()]=n[1].trim())}}),t}function ds(e){let t="";if(te(e))t=e;else if(N(e))for(let s=0;s<e.length;s++){const n=ds(e[s]);n&&(t+=n+" ")}else if(ee(e))for(const s in e)e[s]&&(t+=s+" ");return t.trim()}const Wr=is("itemscope,allowfullscreen,formnovalidate,ismap,nomodule,novalidate,readonly");function tn(e){return!!e||e===""}/**
* @vue/reactivity v3.5.22
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/let he;class Br{constructor(t=!1){this.detached=t,this._active=!0,this._on=0,this.effects=[],this.cleanups=[],this._isPaused=!1,this.parent=he,!t&&he&&(this.index=(he.scopes||(he.scopes=[])).push(this)-1)}get active(){return this._active}pause(){if(this._active){this._isPaused=!0;let t,s;if(this.scopes)for(t=0,s=this.scopes.length;t<s;t++)this.scopes[t].pause();for(t=0,s=this.effects.length;t<s;t++)this.effects[t].pause()}}resume(){if(this._active&&this._isPaused){this._isPaused=!1;let t,s;if(this.scopes)for(t=0,s=this.scopes.length;t<s;t++)this.scopes[t].resume();for(t=0,s=this.effects.length;t<s;t++)this.effects[t].resume()}}run(t){if(this._active){const s=he;try{return he=this,t()}finally{he=s}}}on(){++this._on===1&&(this.prevScope=he,he=this)}off(){this._on>0&&--this._on===0&&(he=this.prevScope,this.prevScope=void 0)}stop(t){if(this._active){this._active=!1;let s,n;for(s=0,n=this.effects.length;s<n;s++)this.effects[s].stop();for(this.effects.length=0,s=0,n=this.cleanups.length;s<n;s++)this.cleanups[s]();if(this.cleanups.length=0,this.scopes){for(s=0,n=this.scopes.length;s<n;s++)this.scopes[s].stop(!0);this.scopes.length=0}if(!this.detached&&this.parent&&!t){const r=this.parent.scopes.pop();r&&r!==this&&(this.parent.scopes[this.index]=r,r.index=this.index)}this.parent=void 0}}}function Kr(){return he}let J;const ps=new WeakSet;class sn{constructor(t){this.fn=t,this.deps=void 0,this.depsTail=void 0,this.flags=5,this.next=void 0,this.cleanup=void 0,this.scheduler=void 0,he&&he.active&&he.effects.push(this)}pause(){this.flags|=64}resume(){this.flags&64&&(this.flags&=-65,ps.has(this)&&(ps.delete(this),this.trigger()))}notify(){this.flags&2&&!(this.flags&32)||this.flags&8||rn(this)}run(){if(!(this.flags&1))return this.fn();this.flags|=2,un(this),on(this);const t=J,s=xe;J=this,xe=!0;try{return this.fn()}finally{ln(this),J=t,xe=s,this.flags&=-3}}stop(){if(this.flags&1){for(let t=this.deps;t;t=t.nextDep)ms(t);this.deps=this.depsTail=void 0,un(this),this.onStop&&this.onStop(),this.flags&=-2}}trigger(){this.flags&64?ps.add(this):this.scheduler?this.scheduler():this.runIfDirty()}runIfDirty(){_s(this)&&this.run()}get dirty(){return _s(this)}}let nn=0,_t,mt;function rn(e,t=!1){if(e.flags|=8,t){e.next=mt,mt=e;return}e.next=_t,_t=e}function hs(){nn++}function gs(){if(--nn>0)return;if(mt){let t=mt;for(mt=void 0;t;){const s=t.next;t.next=void 0,t.flags&=-9,t=s}}let e;for(;_t;){let t=_t;for(_t=void 0;t;){const s=t.next;if(t.next=void 0,t.flags&=-9,t.flags&1)try{t.trigger()}catch(n){e||(e=n)}t=s}}if(e)throw e}function on(e){for(let t=e.deps;t;t=t.nextDep)t.version=-1,t.prevActiveLink=t.dep.activeLink,t.dep.activeLink=t}function ln(e){let t,s=e.depsTail,n=s;for(;n;){const r=n.prevDep;n.version===-1?(n===s&&(s=r),ms(n),Yr(n)):t=n,n.dep.activeLink=n.prevActiveLink,n.prevActiveLink=void 0,n=r}e.deps=t,e.depsTail=s}function _s(e){for(let t=e.deps;t;t=t.nextDep)if(t.dep.version!==t.version||t.dep.computed&&(cn(t.dep.computed)||t.dep.version!==t.version))return!0;return!!e._dirty}function cn(e){if(e.flags&4&&!(e.flags&16)||(e.flags&=-17,e.globalVersion===bt)||(e.globalVersion=bt,!e.isSSR&&e.flags&128&&(!e.deps&&!e._dirty||!_s(e))))return;e.flags|=2;const t=e.dep,s=J,n=xe;J=e,xe=!0;try{on(e);const r=e.fn(e._value);(t.version===0||We(r,e._value))&&(e.flags|=128,e._value=r,t.version++)}catch(r){throw t.version++,r}finally{J=s,xe=n,ln(e),e.flags&=-3}}function ms(e,t=!1){const{dep:s,prevSub:n,nextSub:r}=e;if(n&&(n.nextSub=r,e.prevSub=void 0),r&&(r.prevSub=n,e.nextSub=void 0),s.subs===e&&(s.subs=n,!n&&s.computed)){s.computed.flags&=-5;for(let i=s.computed.deps;i;i=i.nextDep)ms(i,!0)}!t&&!--s.sc&&s.map&&s.map.delete(s.key)}function Yr(e){const{prevDep:t,nextDep:s}=e;t&&(t.nextDep=s,e.prevDep=void 0),s&&(s.prevDep=t,e.nextDep=void 0)}let xe=!0;const an=[];function Pe(){an.push(xe),xe=!1}function Ee(){const e=an.pop();xe=e===void 0?!0:e}function un(e){const{cleanup:t}=e;if(e.cleanup=void 0,t){const s=J;J=void 0;try{t()}finally{J=s}}}let bt=0;class Gr{constructor(t,s){this.sub=t,this.dep=s,this.version=s.version,this.nextDep=this.prevDep=this.nextSub=this.prevSub=this.prevActiveLink=void 0}}class bs{constructor(t){this.computed=t,this.version=0,this.activeLink=void 0,this.subs=void 0,this.map=void 0,this.key=void 0,this.sc=0,this.__v_skip=!0}track(t){if(!J||!xe||J===this.computed)return;let s=this.activeLink;if(s===void 0||s.sub!==J)s=this.activeLink=new Gr(J,this),J.deps?(s.prevDep=J.depsTail,J.depsTail.nextDep=s,J.depsTail=s):J.deps=J.depsTail=s,fn(s);else if(s.version===-1&&(s.version=this.version,s.nextDep)){const n=s.nextDep;n.prevDep=s.prevDep,s.prevDep&&(s.prevDep.nextDep=n),s.prevDep=J.depsTail,s.nextDep=void 0,J.depsTail.nextDep=s,J.depsTail=s,J.deps===s&&(J.deps=n)}return s}trigger(t){this.version++,bt++,this.notify(t)}notify(t){hs();try{for(let s=this.subs;s;s=s.prevSub)s.sub.notify()&&s.sub.dep.notify()}finally{gs()}}}function fn(e){if(e.dep.sc++,e.sub.flags&4){const t=e.dep.computed;if(t&&!e.dep.subs){t.flags|=20;for(let n=t.deps;n;n=n.nextDep)fn(n)}const s=e.dep.subs;s!==e&&(e.prevSub=s,s&&(s.nextSub=e)),e.dep.subs=e}}const vs=new WeakMap,Xe=Symbol(""),ys=Symbol(""),vt=Symbol("");function oe(e,t,s){if(xe&&J){let n=vs.get(e);n||vs.set(e,n=new Map);let r=n.get(s);r||(n.set(s,r=new bs),r.map=n,r.key=s),r.track()}}function He(e,t,s,n,r,i){const o=vs.get(e);if(!o){bt++;return}const l=a=>{a&&a.trigger()};if(hs(),t==="clear")o.forEach(l);else{const a=N(e),d=a&&cs(s);if(a&&s==="length"){const f=Number(n);o.forEach((h,v)=>{(v==="length"||v===vt||!rt(v)&&v>=f)&&l(h)})}else switch((s!==void 0||o.has(void 0))&&l(o.get(s)),d&&l(o.get(vt)),t){case"add":a?d&&l(o.get("length")):(l(o.get(Xe)),ht(e)&&l(o.get(ys)));break;case"delete":a||(l(o.get(Xe)),ht(e)&&l(o.get(ys)));break;case"set":ht(e)&&l(o.get(Xe));break}}gs()}function it(e){const t=j(e);return t===e?t:(oe(t,"iterate",vt),we(e)?t:t.map(ce))}function xs(e){return oe(e=j(e),"iterate",vt),e}const Jr={__proto__:null,[Symbol.iterator](){return ws(this,Symbol.iterator,ce)},concat(...e){return it(this).concat(...e.map(t=>N(t)?it(t):t))},entries(){return ws(this,"entries",e=>(e[1]=ce(e[1]),e))},every(e,t){return $e(this,"every",e,t,void 0,arguments)},filter(e,t){return $e(this,"filter",e,t,s=>s.map(ce),arguments)},find(e,t){return $e(this,"find",e,t,ce,arguments)},findIndex(e,t){return $e(this,"findIndex",e,t,void 0,arguments)},findLast(e,t){return $e(this,"findLast",e,t,ce,arguments)},findLastIndex(e,t){return $e(this,"findLastIndex",e,t,void 0,arguments)},forEach(e,t){return $e(this,"forEach",e,t,void 0,arguments)},includes(...e){return Ss(this,"includes",e)},indexOf(...e){return Ss(this,"indexOf",e)},join(e){return it(this).join(e)},lastIndexOf(...e){return Ss(this,"lastIndexOf",e)},map(e,t){return $e(this,"map",e,t,void 0,arguments)},pop(){return yt(this,"pop")},push(...e){return yt(this,"push",e)},reduce(e,...t){return dn(this,"reduce",e,t)},reduceRight(e,...t){return dn(this,"reduceRight",e,t)},shift(){return yt(this,"shift")},some(e,t){return $e(this,"some",e,t,void 0,arguments)},splice(...e){return yt(this,"splice",e)},toReversed(){return it(this).toReversed()},toSorted(e){return it(this).toSorted(e)},toSpliced(...e){return it(this).toSpliced(...e)},unshift(...e){return yt(this,"unshift",e)},values(){return ws(this,"values",ce)}};function ws(e,t,s){const n=xs(e),r=n[t]();return n!==e&&!we(e)&&(r._next=r.next,r.next=()=>{const i=r._next();return i.done||(i.value=s(i.value)),i}),r}const Qr=Array.prototype;function $e(e,t,s,n,r,i){const o=xs(e),l=o!==e&&!we(e),a=o[t];if(a!==Qr[t]){const h=a.apply(e,i);return l?ce(h):h}let d=s;o!==e&&(l?d=function(h,v){return s.call(this,ce(h),v,e)}:s.length>2&&(d=function(h,v){return s.call(this,h,v,e)}));const f=a.call(o,d,n);return l&&r?r(f):f}function dn(e,t,s,n){const r=xs(e);let i=s;return r!==e&&(we(e)?s.length>3&&(i=function(o,l,a){return s.call(this,o,l,a,e)}):i=function(o,l,a){return s.call(this,o,ce(l),a,e)}),r[t](i,...n)}function Ss(e,t,s){const n=j(e);oe(n,"iterate",vt);const r=n[t](...s);return(r===-1||r===!1)&&Ps(s[0])?(s[0]=j(s[0]),n[t](...s)):r}function yt(e,t,s=[]){Pe(),hs();const n=j(e)[t].apply(e,s);return gs(),Ee(),n}const Xr=is("__proto__,__v_isRef,__isVue"),pn=new Set(Object.getOwnPropertyNames(Symbol).filter(e=>e!=="arguments"&&e!=="caller").map(e=>Symbol[e]).filter(rt));function zr(e){rt(e)||(e=String(e));const t=j(this);return oe(t,"has",e),t.hasOwnProperty(e)}class hn{constructor(t=!1,s=!1){this._isReadonly=t,this._isShallow=s}get(t,s,n){if(s==="__v_skip")return t.__v_skip;const r=this._isReadonly,i=this._isShallow;if(s==="__v_isReactive")return!r;if(s==="__v_isReadonly")return r;if(s==="__v_isShallow")return i;if(s==="__v_raw")return n===(r?i?yn:vn:i?bn:mn).get(t)||Object.getPrototypeOf(t)===Object.getPrototypeOf(n)?t:void 0;const o=N(t);if(!r){let a;if(o&&(a=Jr[s]))return a;if(s==="hasOwnProperty")return zr}const l=Reflect.get(t,s,ne(t)?t:n);if((rt(s)?pn.has(s):Xr(s))||(r||oe(t,"get",s),i))return l;if(ne(l)){const a=o&&cs(s)?l:l.value;return r&&ee(a)?Ts(a):a}return ee(l)?r?Ts(l):Ie(l):l}}class gn extends hn{constructor(t=!1){super(!1,t)}set(t,s,n,r){let i=t[s];if(!this._isShallow){const a=ze(i);if(!we(n)&&!ze(n)&&(i=j(i),n=j(n)),!N(t)&&ne(i)&&!ne(n))return a||(i.value=n),!0}const o=N(t)&&cs(s)?Number(s)<t.length:V(t,s),l=Reflect.set(t,s,n,ne(t)?t:r);return t===j(r)&&(o?We(n,i)&&He(t,"set",s,n):He(t,"add",s,n)),l}deleteProperty(t,s){const n=V(t,s);t[s];const r=Reflect.deleteProperty(t,s);return r&&n&&He(t,"delete",s,void 0),r}has(t,s){const n=Reflect.has(t,s);return(!rt(s)||!pn.has(s))&&oe(t,"has",s),n}ownKeys(t){return oe(t,"iterate",N(t)?"length":Xe),Reflect.ownKeys(t)}}class _n extends hn{constructor(t=!1){super(!0,t)}set(t,s){return!0}deleteProperty(t,s){return!0}}const Zr=new gn,ei=new _n,ti=new gn(!0),si=new _n(!0),Cs=e=>e,Ht=e=>Reflect.getPrototypeOf(e);function ni(e,t,s){return function(...n){const r=this.__v_raw,i=j(r),o=ht(i),l=e==="entries"||e===Symbol.iterator&&o,a=e==="keys"&&o,d=r[e](...n),f=s?Cs:t?Es:ce;return!t&&oe(i,"iterate",a?ys:Xe),{next(){const{value:h,done:v}=d.next();return v?{value:h,done:v}:{value:l?[f(h[0]),f(h[1])]:f(h),done:v}},[Symbol.iterator](){return this}}}}function $t(e){return function(...t){return e==="delete"?!1:e==="clear"?void 0:this}}function ri(e,t){const s={get(r){const i=this.__v_raw,o=j(i),l=j(r);e||(We(r,l)&&oe(o,"get",r),oe(o,"get",l));const{has:a}=Ht(o),d=t?Cs:e?Es:ce;if(a.call(o,r))return d(i.get(r));if(a.call(o,l))return d(i.get(l));i!==o&&i.get(r)},get size(){const r=this.__v_raw;return!e&&oe(j(r),"iterate",Xe),r.size},has(r){const i=this.__v_raw,o=j(i),l=j(r);return e||(We(r,l)&&oe(o,"has",r),oe(o,"has",l)),r===l?i.has(r):i.has(r)||i.has(l)},forEach(r,i){const o=this,l=o.__v_raw,a=j(l),d=t?Cs:e?Es:ce;return!e&&oe(a,"iterate",Xe),l.forEach((f,h)=>r.call(i,d(f),d(h),o))}};return le(s,e?{add:$t("add"),set:$t("set"),delete:$t("delete"),clear:$t("clear")}:{add(r){!t&&!we(r)&&!ze(r)&&(r=j(r));const i=j(this);return Ht(i).has.call(i,r)||(i.add(r),He(i,"add",r,r)),this},set(r,i){!t&&!we(i)&&!ze(i)&&(i=j(i));const o=j(this),{has:l,get:a}=Ht(o);let d=l.call(o,r);d||(r=j(r),d=l.call(o,r));const f=a.call(o,r);return o.set(r,i),d?We(i,f)&&He(o,"set",r,i):He(o,"add",r,i),this},delete(r){const i=j(this),{has:o,get:l}=Ht(i);let a=o.call(i,r);a||(r=j(r),a=o.call(i,r)),l&&l.call(i,r);const d=i.delete(r);return a&&He(i,"delete",r,void 0),d},clear(){const r=j(this),i=r.size!==0,o=r.clear();return i&&He(r,"clear",void 0,void 0),o}}),["keys","values","entries",Symbol.iterator].forEach(r=>{s[r]=ni(r,e,t)}),s}function jt(e,t){const s=ri(e,t);return(n,r,i)=>r==="__v_isReactive"?!e:r==="__v_isReadonly"?e:r==="__v_raw"?n:Reflect.get(V(s,r)&&r in n?s:n,r,i)}const ii={get:jt(!1,!1)},oi={get:jt(!1,!0)},li={get:jt(!0,!1)},ci={get:jt(!0,!0)},mn=new WeakMap,bn=new WeakMap,vn=new WeakMap,yn=new WeakMap;function ai(e){switch(e){case"Object":case"Array":return 1;case"Map":case"Set":case"WeakMap":case"WeakSet":return 2;default:return 0}}function ui(e){return e.__v_skip||!Object.isExtensible(e)?0:ai(Nr(e))}function Ie(e){return ze(e)?e:Ut(e,!1,Zr,ii,mn)}function fi(e){return Ut(e,!1,ti,oi,bn)}function Ts(e){return Ut(e,!0,ei,li,vn)}function dl(e){return Ut(e,!0,si,ci,yn)}function Ut(e,t,s,n,r){if(!ee(e)||e.__v_raw&&!(t&&e.__v_isReactive))return e;const i=ui(e);if(i===0)return e;const o=r.get(e);if(o)return o;const l=new Proxy(e,i===2?n:s);return r.set(e,l),l}function xt(e){return ze(e)?xt(e.__v_raw):!!(e&&e.__v_isReactive)}function ze(e){return!!(e&&e.__v_isReadonly)}function we(e){return!!(e&&e.__v_isShallow)}function Ps(e){return e?!!e.__v_raw:!1}function j(e){const t=e&&e.__v_raw;return t?j(t):e}function di(e){return!V(e,"__v_skip")&&Object.isExtensible(e)&&Zs(e,"__v_skip",!0),e}const ce=e=>ee(e)?Ie(e):e,Es=e=>ee(e)?Ts(e):e;function ne(e){return e?e.__v_isRef===!0:!1}function Is(e){return pi(e,!1)}function pi(e,t){return ne(e)?e:new hi(e,t)}class hi{constructor(t,s){this.dep=new bs,this.__v_isRef=!0,this.__v_isShallow=!1,this._rawValue=s?t:j(t),this._value=s?t:ce(t),this.__v_isShallow=s}get value(){return this.dep.track(),this._value}set value(t){const s=this._rawValue,n=this.__v_isShallow||we(t)||ze(t);t=n?t:j(t),We(t,s)&&(this._rawValue=t,this._value=n?t:ce(t),this.dep.trigger())}}function gi(e){return ne(e)?e.value:e}const _i={get:(e,t,s)=>t==="__v_raw"?e:gi(Reflect.get(e,t,s)),set:(e,t,s,n)=>{const r=e[t];return ne(r)&&!ne(s)?(r.value=s,!0):Reflect.set(e,t,s,n)}};function xn(e){return xt(e)?e:new Proxy(e,_i)}class mi{constructor(t,s,n){this.fn=t,this.setter=s,this._value=void 0,this.dep=new bs(this),this.__v_isRef=!0,this.deps=void 0,this.depsTail=void 0,this.flags=16,this.globalVersion=bt-1,this.next=void 0,this.effect=this,this.__v_isReadonly=!s,this.isSSR=n}notify(){if(this.flags|=16,!(this.flags&8)&&J!==this)return rn(this,!0),!0}get value(){const t=this.dep.track();return cn(this),t&&(t.version=this.dep.version),this._value}set value(t){this.setter&&this.setter(t)}}function bi(e,t,s=!1){let n,r;return F(e)?n=e:(n=e.get,r=e.set),new mi(n,r,s)}const qt={},Vt=new WeakMap;let Ze;function vi(e,t=!1,s=Ze){if(s){let n=Vt.get(s);n||Vt.set(s,n=[]),n.push(e)}}function yi(e,t,s=G){const{immediate:n,deep:r,once:i,scheduler:o,augmentJob:l,call:a}=s,d=T=>r?T:we(T)||r===!1||r===0?Be(T,1):Be(T);let f,h,v,w,P=!1,k=!1;if(ne(e)?(h=()=>e.value,P=we(e)):xt(e)?(h=()=>d(e),P=!0):N(e)?(k=!0,P=e.some(T=>xt(T)||we(T)),h=()=>e.map(T=>{if(ne(T))return T.value;if(xt(T))return d(T);if(F(T))return a?a(T,2):T()})):F(e)?t?h=a?()=>a(e,2):e:h=()=>{if(v){Pe();try{v()}finally{Ee()}}const T=Ze;Ze=f;try{return a?a(e,3,[w]):e(w)}finally{Ze=T}}:h=Te,t&&r){const T=h,K=r===!0?1/0:r;h=()=>Be(T(),K)}const Z=Kr(),R=()=>{f.stop(),Z&&Z.active&&ls(Z.effects,f)};if(i&&t){const T=t;t=(...K)=>{T(...K),R()}}let O=k?new Array(e.length).fill(qt):qt;const U=T=>{if(!(!(f.flags&1)||!f.dirty&&!T))if(t){const K=f.run();if(r||P||(k?K.some((de,re)=>We(de,O[re])):We(K,O))){v&&v();const de=Ze;Ze=f;try{const re=[K,O===qt?void 0:k&&O[0]===qt?[]:O,w];O=K,a?a(t,3,re):t(...re)}finally{Ze=de}}}else f.run()};return l&&l(U),f=new sn(h),f.scheduler=o?()=>o(U,!1):U,w=T=>vi(T,!1,f),v=f.onStop=()=>{const T=Vt.get(f);if(T){if(a)a(T,4);else for(const K of T)K();Vt.delete(f)}},t?n?U(!0):O=f.run():o?o(U.bind(null,!0),!0):f.run(),R.pause=f.pause.bind(f),R.resume=f.resume.bind(f),R.stop=R,R}function Be(e,t=1/0,s){if(t<=0||!ee(e)||e.__v_skip||(s=s||new Map,(s.get(e)||0)>=t))return e;if(s.set(e,t),t--,ne(e))Be(e.value,t,s);else if(N(e))for(let n=0;n<e.length;n++)Be(e[n],t,s);else if(Rr(e)||ht(e))e.forEach(n=>{Be(n,t,s)});else if(Dr(e)){for(const n in e)Be(e[n],t,s);for(const n of Object.getOwnPropertySymbols(e))Object.prototype.propertyIsEnumerable.call(e,n)&&Be(e[n],t,s)}return e}/**
* @vue/runtime-core v3.5.22
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/const wt=[];let As=!1;function pl(e,...t){if(As)return;As=!0,Pe();const s=wt.length?wt[wt.length-1].component:null,n=s&&s.appContext.config.warnHandler,r=xi();if(n)ot(n,s,11,[e+t.map(i=>{var o,l;return(l=(o=i.toString)==null?void 0:o.call(i))!=null?l:JSON.stringify(i)}).join(""),s&&s.proxy,r.map(({vnode:i})=>`at <${dr(s,i.type)}>`).join(`
`),r]);else{const i=[`[Vue warn]: ${e}`,...t];r.length&&i.push(`
`,...wi(r)),console.warn(...i)}Ee(),As=!1}function xi(){let e=wt[wt.length-1];if(!e)return[];const t=[];for(;e;){const s=t[0];s&&s.vnode===e?s.recurseCount++:t.push({vnode:e,recurseCount:0});const n=e.component&&e.component.parent;e=n&&n.vnode}return t}function wi(e){const t=[];return e.forEach((s,n)=>{t.push(...n===0?[]:[`
`],...Si(s))}),t}function Si({vnode:e,recurseCount:t}){const s=t>0?`... (${t} recursive calls)`:"",n=e.component?e.component.parent==null:!1,r=` at <${dr(e.component,e.type,n)}`,i=">"+s;return e.props?[r,...Ci(e.props),i]:[r+i]}function Ci(e){const t=[],s=Object.keys(e);return s.slice(0,3).forEach(n=>{t.push(...wn(n,e[n]))}),s.length>3&&t.push(" ..."),t}function wn(e,t,s){return te(t)?(t=JSON.stringify(t),s?t:[`${e}=${t}`]):typeof t=="number"||typeof t=="boolean"||t==null?s?t:[`${e}=${t}`]:ne(t)?(t=wn(e,j(t.value),!0),s?t:[`${e}=Ref<`,t,">"]):F(t)?[`${e}=fn${t.name?`<${t.name}>`:""}`]:(t=j(t),s?t:[`${e}=`,t])}function ot(e,t,s,n){try{return n?e(...n):e()}catch(r){Wt(r,t,s)}}function Ae(e,t,s,n){if(F(e)){const r=ot(e,t,s,n);return r&&Xs(r)&&r.catch(i=>{Wt(i,t,s)}),r}if(N(e)){const r=[];for(let i=0;i<e.length;i++)r.push(Ae(e[i],t,s,n));return r}}function Wt(e,t,s,n=!0){const r=t?t.vnode:null,{errorHandler:i,throwUnhandledErrorInProduction:o}=t&&t.appContext.config||G;if(t){let l=t.parent;const a=t.proxy,d=`https://vuejs.org/error-reference/#runtime-${s}`;for(;l;){const f=l.ec;if(f){for(let h=0;h<f.length;h++)if(f[h](e,a,d)===!1)return}l=l.parent}if(i){Pe(),ot(i,null,10,[e,a,d]),Ee();return}}Ti(e,s,r,n,o)}function Ti(e,t,s,n=!0,r=!1){if(r)throw e;console.error(e)}const ae=[];let Oe=-1;const lt=[];let Ke=null,ct=0;const Sn=Promise.resolve();let Bt=null;function Pi(e){const t=Bt||Sn;return e?t.then(this?e.bind(this):e):t}function Ei(e){let t=Oe+1,s=ae.length;for(;t<s;){const n=t+s>>>1,r=ae[n],i=St(r);i<e||i===e&&r.flags&2?t=n+1:s=n}return t}function Os(e){if(!(e.flags&1)){const t=St(e),s=ae[ae.length-1];!s||!(e.flags&2)&&t>=St(s)?ae.push(e):ae.splice(Ei(t),0,e),e.flags|=1,Cn()}}function Cn(){Bt||(Bt=Sn.then(En))}function Ii(e){N(e)?lt.push(...e):Ke&&e.id===-1?Ke.splice(ct+1,0,e):e.flags&1||(lt.push(e),e.flags|=1),Cn()}function Tn(e,t,s=Oe+1){for(;s<ae.length;s++){const n=ae[s];if(n&&n.flags&2){if(e&&n.id!==e.uid)continue;ae.splice(s,1),s--,n.flags&4&&(n.flags&=-2),n(),n.flags&4||(n.flags&=-2)}}}function Pn(e){if(lt.length){const t=[...new Set(lt)].sort((s,n)=>St(s)-St(n));if(lt.length=0,Ke){Ke.push(...t);return}for(Ke=t,ct=0;ct<Ke.length;ct++){const s=Ke[ct];s.flags&4&&(s.flags&=-2),s.flags&8||s(),s.flags&=-2}Ke=null,ct=0}}const St=e=>e.id==null?e.flags&2?-1:1/0:e.id;function En(e){try{for(Oe=0;Oe<ae.length;Oe++){const t=ae[Oe];t&&!(t.flags&8)&&(t.flags&4&&(t.flags&=-2),ot(t,t.i,t.i?15:14),t.flags&4||(t.flags&=-2))}}finally{for(;Oe<ae.length;Oe++){const t=ae[Oe];t&&(t.flags&=-2)}Oe=-1,ae.length=0,Pn(),Bt=null,(ae.length||lt.length)&&En()}}let Me=null,In=null;function Kt(e){const t=Me;return Me=e,In=e&&e.type.__scopeId||null,t}function Ai(e,t=Me,s){if(!t||e._n)return e;const n=(...r)=>{n._d&&ir(-1);const i=Kt(t);let o;try{o=e(...r)}finally{Kt(i),n._d&&ir(1)}return o};return n._n=!0,n._c=!0,n._d=!0,n}function et(e,t,s,n){const r=e.dirs,i=t&&t.dirs;for(let o=0;o<r.length;o++){const l=r[o];i&&(l.oldValue=i[o].value);let a=l.dir[n];a&&(Pe(),Ae(a,s,8,[e.el,l,e,t]),Ee())}}const Oi=Symbol("_vte"),Mi=e=>e.__isTeleport,ki=Symbol("_leaveCb");function Ms(e,t){e.shapeFlag&6&&e.component?(e.transition=t,Ms(e.component.subTree,t)):e.shapeFlag&128?(e.ssContent.transition=t.clone(e.ssContent),e.ssFallback.transition=t.clone(e.ssFallback)):e.transition=t}function An(e){e.ids=[e.ids[0]+e.ids[2]+++"-",0,0]}const Yt=new WeakMap;function Ct(e,t,s,n,r=!1){if(N(e)){e.forEach((P,k)=>Ct(P,t&&(N(t)?t[k]:t),s,n,r));return}if(Tt(n)&&!r){n.shapeFlag&512&&n.type.__asyncResolved&&n.component.subTree.component&&Ct(e,t,s,n.component.subTree);return}const i=n.shapeFlag&4?Ws(n.component):n.el,o=r?null:i,{i:l,r:a}=e,d=t&&t.r,f=l.refs===G?l.refs={}:l.refs,h=l.setupState,v=j(h),w=h===G?Qs:P=>V(v,P);if(d!=null&&d!==a){if(On(t),te(d))f[d]=null,w(d)&&(h[d]=null);else if(ne(d)){d.value=null;const P=t;P.k&&(f[P.k]=null)}}if(F(a))ot(a,l,12,[o,f]);else{const P=te(a),k=ne(a);if(P||k){const Z=()=>{if(e.f){const R=P?w(a)?h[a]:f[a]:a.value;if(r)N(R)&&ls(R,i);else if(N(R))R.includes(i)||R.push(i);else if(P)f[a]=[i],w(a)&&(h[a]=f[a]);else{const O=[i];a.value=O,e.k&&(f[e.k]=O)}}else P?(f[a]=o,w(a)&&(h[a]=o)):k&&(a.value=o,e.k&&(f[e.k]=o))};if(o){const R=()=>{Z(),Yt.delete(e)};R.id=-1,Yt.set(e,R),be(R,s)}else On(e),Z()}}}function On(e){const t=Yt.get(e);t&&(t.flags|=8,Yt.delete(e))}Lt().requestIdleCallback,Lt().cancelIdleCallback;const Tt=e=>!!e.type.__asyncLoader,Mn=e=>e.type.__isKeepAlive;function Ri(e,t){kn(e,"a",t)}function Fi(e,t){kn(e,"da",t)}function kn(e,t,s=fe){const n=e.__wdc||(e.__wdc=()=>{let r=s;for(;r;){if(r.isDeactivated)return;r=r.parent}return e()});if(Gt(t,n,s),s){let r=s.parent;for(;r&&r.parent;)Mn(r.parent.vnode)&&Ni(n,t,s,r),r=r.parent}}function Ni(e,t,s,n){const r=Gt(t,e,n,!0);Rn(()=>{ls(n[t],r)},s)}function Gt(e,t,s=fe,n=!1){if(s){const r=s[e]||(s[e]=[]),i=t.__weh||(t.__weh=(...o)=>{Pe();const l=Ot(s),a=Ae(t,s,e,o);return l(),Ee(),a});return n?r.unshift(i):r.push(i),i}}const je=e=>(t,s=fe)=>{(!Mt||e==="sp")&&Gt(e,(...n)=>t(...n),s)},Di=je("bm"),Li=je("m"),Hi=je("bu"),$i=je("u"),ji=je("bum"),Rn=je("um"),Ui=je("sp"),qi=je("rtg"),Vi=je("rtc");function Wi(e,t=fe){Gt("ec",e,t)}const Bi=Symbol.for("v-ndc"),ks=e=>e?ar(e)?Ws(e):ks(e.parent):null,Pt=le(Object.create(null),{$:e=>e,$el:e=>e.vnode.el,$data:e=>e.data,$props:e=>e.props,$attrs:e=>e.attrs,$slots:e=>e.slots,$refs:e=>e.refs,$parent:e=>ks(e.parent),$root:e=>ks(e.root),$host:e=>e.ce,$emit:e=>e.emit,$options:e=>Ln(e),$forceUpdate:e=>e.f||(e.f=()=>{Os(e.update)}),$nextTick:e=>e.n||(e.n=Pi.bind(e.proxy)),$watch:e=>ho.bind(e)}),Rs=(e,t)=>e!==G&&!e.__isScriptSetup&&V(e,t),Ki={get({_:e},t){if(t==="__v_skip")return!0;const{ctx:s,setupState:n,data:r,props:i,accessCache:o,type:l,appContext:a}=e;let d;if(t[0]!=="$"){const w=o[t];if(w!==void 0)switch(w){case 1:return n[t];case 2:return r[t];case 4:return s[t];case 3:return i[t]}else{if(Rs(n,t))return o[t]=1,n[t];if(r!==G&&V(r,t))return o[t]=2,r[t];if((d=e.propsOptions[0])&&V(d,t))return o[t]=3,i[t];if(s!==G&&V(s,t))return o[t]=4,s[t];Fs&&(o[t]=0)}}const f=Pt[t];let h,v;if(f)return t==="$attrs"&&oe(e.attrs,"get",""),f(e);if((h=l.__cssModules)&&(h=h[t]))return h;if(s!==G&&V(s,t))return o[t]=4,s[t];if(v=a.config.globalProperties,V(v,t))return v[t]},set({_:e},t,s){const{data:n,setupState:r,ctx:i}=e;return Rs(r,t)?(r[t]=s,!0):n!==G&&V(n,t)?(n[t]=s,!0):V(e.props,t)||t[0]==="$"&&t.slice(1)in e?!1:(i[t]=s,!0)},has({_:{data:e,setupState:t,accessCache:s,ctx:n,appContext:r,propsOptions:i,type:o}},l){let a,d;return!!(s[l]||e!==G&&l[0]!=="$"&&V(e,l)||Rs(t,l)||(a=i[0])&&V(a,l)||V(n,l)||V(Pt,l)||V(r.config.globalProperties,l)||(d=o.__cssModules)&&d[l])},defineProperty(e,t,s){return s.get!=null?e._.accessCache[t]=0:V(s,"value")&&this.set(e,t,s.value,null),Reflect.defineProperty(e,t,s)}};function Fn(e){return N(e)?e.reduce((t,s)=>(t[s]=null,t),{}):e}let Fs=!0;function Yi(e){const t=Ln(e),s=e.proxy,n=e.ctx;Fs=!1,t.beforeCreate&&Nn(t.beforeCreate,e,"bc");const{data:r,computed:i,methods:o,watch:l,provide:a,inject:d,created:f,beforeMount:h,mounted:v,beforeUpdate:w,updated:P,activated:k,deactivated:Z,beforeDestroy:R,beforeUnmount:O,destroyed:U,unmounted:T,render:K,renderTracked:de,renderTriggered:re,errorCaptured:ie,serverPrefetch:ve,expose:L,inheritAttrs:pe,components:E,directives:X,filters:Se}=t;if(d&&Gi(d,n,null),o)for(const q in o){const W=o[q];F(W)&&(n[q]=W.bind(s))}if(r){const q=r.call(s,s);ee(q)&&(e.data=Ie(q))}if(Fs=!0,i)for(const q in i){const W=i[q],ge=F(W)?W.bind(s,s):F(W.get)?W.get.bind(s,s):Te,D=!F(W)&&F(W.set)?W.set.bind(s):Te,Y=se({get:ge,set:D});Object.defineProperty(n,q,{enumerable:!0,configurable:!0,get:()=>Y.value,set:z=>Y.value=z})}if(l)for(const q in l)Dn(l[q],n,s,q);if(a){const q=F(a)?a.call(s):a;Reflect.ownKeys(q).forEach(W=>{eo(W,q[W])})}f&&Nn(f,e,"c");function H(q,W){N(W)?W.forEach(ge=>q(ge.bind(s))):W&&q(W.bind(s))}if(H(Di,h),H(Li,v),H(Hi,w),H($i,P),H(Ri,k),H(Fi,Z),H(Wi,ie),H(Vi,de),H(qi,re),H(ji,O),H(Rn,T),H(Ui,ve),N(L))if(L.length){const q=e.exposed||(e.exposed={});L.forEach(W=>{Object.defineProperty(q,W,{get:()=>s[W],set:ge=>s[W]=ge,enumerable:!0})})}else e.exposed||(e.exposed={});K&&e.render===Te&&(e.render=K),pe!=null&&(e.inheritAttrs=pe),E&&(e.components=E),X&&(e.directives=X),ve&&An(e)}function Gi(e,t,s=Te){N(e)&&(e=Ns(e));for(const n in e){const r=e[n];let i;ee(r)?"default"in r?i=Qt(r.from||n,r.default,!0):i=Qt(r.from||n):i=Qt(r),ne(i)?Object.defineProperty(t,n,{enumerable:!0,configurable:!0,get:()=>i.value,set:o=>i.value=o}):t[n]=i}}function Nn(e,t,s){Ae(N(e)?e.map(n=>n.bind(t.proxy)):e.bind(t.proxy),t,s)}function Dn(e,t,s,n){let r=n.includes(".")?er(s,n):()=>s[n];if(te(e)){const i=t[e];F(i)&&It(r,i)}else if(F(e))It(r,e.bind(s));else if(ee(e))if(N(e))e.forEach(i=>Dn(i,t,s,n));else{const i=F(e.handler)?e.handler.bind(s):t[e.handler];F(i)&&It(r,i,e)}}function Ln(e){const t=e.type,{mixins:s,extends:n}=t,{mixins:r,optionsCache:i,config:{optionMergeStrategies:o}}=e.appContext,l=i.get(t);let a;return l?a=l:!r.length&&!s&&!n?a=t:(a={},r.length&&r.forEach(d=>Jt(a,d,o,!0)),Jt(a,t,o)),ee(t)&&i.set(t,a),a}function Jt(e,t,s,n=!1){const{mixins:r,extends:i}=t;i&&Jt(e,i,s,!0),r&&r.forEach(o=>Jt(e,o,s,!0));for(const o in t)if(!(n&&o==="expose")){const l=Ji[o]||s&&s[o];e[o]=l?l(e[o],t[o]):t[o]}return e}const Ji={data:Hn,props:$n,emits:$n,methods:Et,computed:Et,beforeCreate:ue,created:ue,beforeMount:ue,mounted:ue,beforeUpdate:ue,updated:ue,beforeDestroy:ue,beforeUnmount:ue,destroyed:ue,unmounted:ue,activated:ue,deactivated:ue,errorCaptured:ue,serverPrefetch:ue,components:Et,directives:Et,watch:Xi,provide:Hn,inject:Qi};function Hn(e,t){return t?e?function(){return le(F(e)?e.call(this,this):e,F(t)?t.call(this,this):t)}:t:e}function Qi(e,t){return Et(Ns(e),Ns(t))}function Ns(e){if(N(e)){const t={};for(let s=0;s<e.length;s++)t[e[s]]=e[s];return t}return e}function ue(e,t){return e?[...new Set([].concat(e,t))]:t}function Et(e,t){return e?le(Object.create(null),e,t):t}function $n(e,t){return e?N(e)&&N(t)?[...new Set([...e,...t])]:le(Object.create(null),Fn(e),Fn(t!=null?t:{})):t}function Xi(e,t){if(!e)return t;if(!t)return e;const s=le(Object.create(null),e);for(const n in t)s[n]=ue(e[n],t[n]);return s}function jn(){return{app:null,config:{isNativeTag:Qs,performance:!1,globalProperties:{},optionMergeStrategies:{},errorHandler:void 0,warnHandler:void 0,compilerOptions:{}},mixins:[],components:{},directives:{},provides:Object.create(null),optionsCache:new WeakMap,propsCache:new WeakMap,emitsCache:new WeakMap}}let zi=0;function Zi(e,t){return function(n,r=null){F(n)||(n=le({},n)),r!=null&&!ee(r)&&(r=null);const i=jn(),o=new WeakSet,l=[];let a=!1;const d=i.app={_uid:zi++,_component:n,_props:r,_container:null,_context:i,_instance:null,version:jo,get config(){return i.config},set config(f){},use(f,...h){return o.has(f)||(f&&F(f.install)?(o.add(f),f.install(d,...h)):F(f)&&(o.add(f),f(d,...h))),d},mixin(f){return i.mixins.includes(f)||i.mixins.push(f),d},component(f,h){return h?(i.components[f]=h,d):i.components[f]},directive(f,h){return h?(i.directives[f]=h,d):i.directives[f]},mount(f,h,v){if(!a){const w=d._ceVNode||st(n,r);return w.appContext=i,v===!0?v="svg":v===!1&&(v=void 0),e(w,f,v),a=!0,d._container=f,f.__vue_app__=d,Ws(w.component)}},onUnmount(f){l.push(f)},unmount(){a&&(Ae(l,d._instance,16),e(null,d._container),delete d._container.__vue_app__)},provide(f,h){return i.provides[f]=h,d},runWithContext(f){const h=at;at=d;try{return f()}finally{at=h}}};return d}}let at=null;function eo(e,t){if(fe){let s=fe.provides;const n=fe.parent&&fe.parent.provides;n===s&&(s=fe.provides=Object.create(n)),s[e]=t}}function Qt(e,t,s=!1){const n=Mo();if(n||at){let r=at?at._context.provides:n?n.parent==null||n.ce?n.vnode.appContext&&n.vnode.appContext.provides:n.parent.provides:void 0;if(r&&e in r)return r[e];if(arguments.length>1)return s&&F(t)?t.call(n&&n.proxy):t}}const Un={},qn=()=>Object.create(Un),Vn=e=>Object.getPrototypeOf(e)===Un;function to(e,t,s,n=!1){const r={},i=qn();e.propsDefaults=Object.create(null),Wn(e,t,r,i);for(const o in e.propsOptions[0])o in r||(r[o]=void 0);s?e.props=n?r:fi(r):e.type.props?e.props=r:e.props=i,e.attrs=i}function so(e,t,s,n){const{props:r,attrs:i,vnode:{patchFlag:o}}=e,l=j(r),[a]=e.propsOptions;let d=!1;if((n||o>0)&&!(o&16)){if(o&8){const f=e.vnode.dynamicProps;for(let h=0;h<f.length;h++){let v=f[h];if(Xt(e.emitsOptions,v))continue;const w=t[v];if(a)if(V(i,v))w!==i[v]&&(i[v]=w,d=!0);else{const P=Ve(v);r[P]=Ds(a,l,P,w,e,!1)}else w!==i[v]&&(i[v]=w,d=!0)}}}else{Wn(e,t,r,i)&&(d=!0);let f;for(const h in l)(!t||!V(t,h)&&((f=Qe(h))===h||!V(t,f)))&&(a?s&&(s[h]!==void 0||s[f]!==void 0)&&(r[h]=Ds(a,l,h,void 0,e,!0)):delete r[h]);if(i!==l)for(const h in i)(!t||!V(t,h))&&(delete i[h],d=!0)}d&&He(e.attrs,"set","")}function Wn(e,t,s,n){const[r,i]=e.propsOptions;let o=!1,l;if(t)for(let a in t){if(gt(a))continue;const d=t[a];let f;r&&V(r,f=Ve(a))?!i||!i.includes(f)?s[f]=d:(l||(l={}))[f]=d:Xt(e.emitsOptions,a)||(!(a in n)||d!==n[a])&&(n[a]=d,o=!0)}if(i){const a=j(s),d=l||G;for(let f=0;f<i.length;f++){const h=i[f];s[h]=Ds(r,a,h,d[h],e,!V(d,h))}}return o}function Ds(e,t,s,n,r,i){const o=e[s];if(o!=null){const l=V(o,"default");if(l&&n===void 0){const a=o.default;if(o.type!==Function&&!o.skipFactory&&F(a)){const{propsDefaults:d}=r;if(s in d)n=d[s];else{const f=Ot(r);n=d[s]=a.call(null,t),f()}}else n=a;r.ce&&r.ce._setProp(s,n)}o[0]&&(i&&!l?n=!1:o[1]&&(n===""||n===Qe(s))&&(n=!0))}return n}const no=new WeakMap;function Bn(e,t,s=!1){const n=s?no:t.propsCache,r=n.get(e);if(r)return r;const i=e.props,o={},l=[];let a=!1;if(!F(e)){const f=h=>{a=!0;const[v,w]=Bn(h,t,!0);le(o,v),w&&l.push(...w)};!s&&t.mixins.length&&t.mixins.forEach(f),e.extends&&f(e.extends),e.mixins&&e.mixins.forEach(f)}if(!i&&!a)return ee(e)&&n.set(e,pt),pt;if(N(i))for(let f=0;f<i.length;f++){const h=Ve(i[f]);Kn(h)&&(o[h]=G)}else if(i)for(const f in i){const h=Ve(f);if(Kn(h)){const v=i[f],w=o[h]=N(v)||F(v)?{type:v}:le({},v),P=w.type;let k=!1,Z=!0;if(N(P))for(let R=0;R<P.length;++R){const O=P[R],U=F(O)&&O.name;if(U==="Boolean"){k=!0;break}else U==="String"&&(Z=!1)}else k=F(P)&&P.name==="Boolean";w[0]=k,w[1]=Z,(k||V(w,"default"))&&l.push(h)}}const d=[o,l];return ee(e)&&n.set(e,d),d}function Kn(e){return e[0]!=="$"&&!gt(e)}const Ls=e=>e==="_"||e==="_ctx"||e==="$stable",Hs=e=>N(e)?e.map(ke):[ke(e)],ro=(e,t,s)=>{if(t._n)return t;const n=Ai((...r)=>Hs(t(...r)),s);return n._c=!1,n},Yn=(e,t,s)=>{const n=e._ctx;for(const r in e){if(Ls(r))continue;const i=e[r];if(F(i))t[r]=ro(r,i,n);else if(i!=null){const o=Hs(i);t[r]=()=>o}}},Gn=(e,t)=>{const s=Hs(t);e.slots.default=()=>s},Jn=(e,t,s)=>{for(const n in t)(s||!Ls(n))&&(e[n]=t[n])},io=(e,t,s)=>{const n=e.slots=qn();if(e.vnode.shapeFlag&32){const r=t._;r?(Jn(n,t,s),s&&Zs(n,"_",r,!0)):Yn(t,n)}else t&&Gn(e,t)},oo=(e,t,s)=>{const{vnode:n,slots:r}=e;let i=!0,o=G;if(n.shapeFlag&32){const l=t._;l?s&&l===1?i=!1:Jn(r,t,s):(i=!t.$stable,Yn(t,r)),o=t}else t&&(Gn(e,t),o={default:1});if(i)for(const l in r)!Ls(l)&&o[l]==null&&delete r[l]},be=wo;function lo(e){return co(e)}function co(e,t){const s=Lt();s.__VUE__=!0;const{insert:n,remove:r,patchProp:i,createElement:o,createText:l,createComment:a,setText:d,setElementText:f,parentNode:h,nextSibling:v,setScopeId:w=Te,insertStaticContent:P}=e,k=(c,u,p,m=null,g=null,_=null,S=void 0,x=null,y=!!u.dynamicChildren)=>{if(c===u)return;c&&!At(c,u)&&(m=rs(c),z(c,g,_,!0),c=null),u.patchFlag===-2&&(y=!1,u.dynamicChildren=null);const{type:b,ref:A,shapeFlag:C}=u;switch(b){case zt:Z(c,u,p,m);break;case ut:R(c,u,p,m);break;case js:c==null&&O(u,p,m,S);break;case Ue:E(c,u,p,m,g,_,S,x,y);break;default:C&1?K(c,u,p,m,g,_,S,x,y):C&6?X(c,u,p,m,g,_,S,x,y):(C&64||C&128)&&b.process(c,u,p,m,g,_,S,x,y,kt)}A!=null&&g?Ct(A,c&&c.ref,_,u||c,!u):A==null&&c&&c.ref!=null&&Ct(c.ref,null,_,c,!0)},Z=(c,u,p,m)=>{if(c==null)n(u.el=l(u.children),p,m);else{const g=u.el=c.el;u.children!==c.children&&d(g,u.children)}},R=(c,u,p,m)=>{c==null?n(u.el=a(u.children||""),p,m):u.el=c.el},O=(c,u,p,m)=>{[c.el,c.anchor]=P(c.children,u,p,m,c.el,c.anchor)},U=({el:c,anchor:u},p,m)=>{let g;for(;c&&c!==u;)g=v(c),n(c,p,m),c=g;n(u,p,m)},T=({el:c,anchor:u})=>{let p;for(;c&&c!==u;)p=v(c),r(c),c=p;r(u)},K=(c,u,p,m,g,_,S,x,y)=>{u.type==="svg"?S="svg":u.type==="math"&&(S="mathml"),c==null?de(u,p,m,g,_,S,x,y):ve(c,u,g,_,S,x,y)},de=(c,u,p,m,g,_,S,x)=>{let y,b;const{props:A,shapeFlag:C,transition:I,dirs:M}=c;if(y=c.el=o(c.type,_,A&&A.is,A),C&8?f(y,c.children):C&16&&ie(c.children,y,null,m,g,$s(c,_),S,x),M&&et(c,null,m,"created"),re(y,c,c.scopeId,S,m),A){for(const Q in A)Q!=="value"&&!gt(Q)&&i(y,Q,null,A[Q],_,m);"value"in A&&i(y,"value",null,A.value,_),(b=A.onVnodeBeforeMount)&&Re(b,m,c)}M&&et(c,null,m,"beforeMount");const $=ao(g,I);$&&I.beforeEnter(y),n(y,u,p),((b=A&&A.onVnodeMounted)||$||M)&&be(()=>{b&&Re(b,m,c),$&&I.enter(y),M&&et(c,null,m,"mounted")},g)},re=(c,u,p,m,g)=>{if(p&&w(c,p),m)for(let _=0;_<m.length;_++)w(c,m[_]);if(g){let _=g.subTree;if(u===_||rr(_.type)&&(_.ssContent===u||_.ssFallback===u)){const S=g.vnode;re(c,S,S.scopeId,S.slotScopeIds,g.parent)}}},ie=(c,u,p,m,g,_,S,x,y=0)=>{for(let b=y;b<c.length;b++){const A=c[b]=x?Ge(c[b]):ke(c[b]);k(null,A,u,p,m,g,_,S,x)}},ve=(c,u,p,m,g,_,S)=>{const x=u.el=c.el;let{patchFlag:y,dynamicChildren:b,dirs:A}=u;y|=c.patchFlag&16;const C=c.props||G,I=u.props||G;let M;if(p&&tt(p,!1),(M=I.onVnodeBeforeUpdate)&&Re(M,p,u,c),A&&et(u,c,p,"beforeUpdate"),p&&tt(p,!0),(C.innerHTML&&I.innerHTML==null||C.textContent&&I.textContent==null)&&f(x,""),b?L(c.dynamicChildren,b,x,p,m,$s(u,g),_):S||W(c,u,x,null,p,m,$s(u,g),_,!1),y>0){if(y&16)pe(x,C,I,p,g);else if(y&2&&C.class!==I.class&&i(x,"class",null,I.class,g),y&4&&i(x,"style",C.style,I.style,g),y&8){const $=u.dynamicProps;for(let Q=0;Q<$.length;Q++){const B=$[Q],_e=C[B],me=I[B];(me!==_e||B==="value")&&i(x,B,_e,me,g,p)}}y&1&&c.children!==u.children&&f(x,u.children)}else!S&&b==null&&pe(x,C,I,p,g);((M=I.onVnodeUpdated)||A)&&be(()=>{M&&Re(M,p,u,c),A&&et(u,c,p,"updated")},m)},L=(c,u,p,m,g,_,S)=>{for(let x=0;x<u.length;x++){const y=c[x],b=u[x],A=y.el&&(y.type===Ue||!At(y,b)||y.shapeFlag&198)?h(y.el):p;k(y,b,A,null,m,g,_,S,!0)}},pe=(c,u,p,m,g)=>{if(u!==p){if(u!==G)for(const _ in u)!gt(_)&&!(_ in p)&&i(c,_,u[_],null,g,m);for(const _ in p){if(gt(_))continue;const S=p[_],x=u[_];S!==x&&_!=="value"&&i(c,_,x,S,g,m)}"value"in p&&i(c,"value",u.value,p.value,g)}},E=(c,u,p,m,g,_,S,x,y)=>{const b=u.el=c?c.el:l(""),A=u.anchor=c?c.anchor:l("");let{patchFlag:C,dynamicChildren:I,slotScopeIds:M}=u;M&&(x=x?x.concat(M):M),c==null?(n(b,p,m),n(A,p,m),ie(u.children||[],p,A,g,_,S,x,y)):C>0&&C&64&&I&&c.dynamicChildren?(L(c.dynamicChildren,I,p,g,_,S,x),(u.key!=null||g&&u===g.subTree)&&Qn(c,u,!0)):W(c,u,p,A,g,_,S,x,y)},X=(c,u,p,m,g,_,S,x,y)=>{u.slotScopeIds=x,c==null?u.shapeFlag&512?g.ctx.activate(u,p,m,S,y):Se(u,p,m,g,_,S,y):Ce(c,u,y)},Se=(c,u,p,m,g,_,S)=>{const x=c.component=Oo(c,m,g);if(Mn(c)&&(x.ctx.renderer=kt),ko(x,!1,S),x.asyncDep){if(g&&g.registerDep(x,H,S),!c.el){const y=x.subTree=st(ut);R(null,y,u,p),c.placeholder=y.el}}else H(x,c,u,p,g,_,S)},Ce=(c,u,p)=>{const m=u.component=c.component;if(yo(c,u,p))if(m.asyncDep&&!m.asyncResolved){q(m,u,p);return}else m.next=u,m.update();else u.el=c.el,m.vnode=u},H=(c,u,p,m,g,_,S)=>{const x=()=>{if(c.isMounted){let{next:C,bu:I,u:M,parent:$,vnode:Q}=c;{const De=Xn(c);if(De){C&&(C.el=Q.el,q(c,C,S)),De.asyncDep.then(()=>{c.isUnmounted||x()});return}}let B=C,_e;tt(c,!1),C?(C.el=Q.el,q(c,C,S)):C=Q,I&&us(I),(_e=C.props&&C.props.onVnodeBeforeUpdate)&&Re(_e,$,C,Q),tt(c,!0);const me=sr(c),Ne=c.subTree;c.subTree=me,k(Ne,me,h(Ne.el),rs(Ne),c,g,_),C.el=me.el,B===null&&xo(c,me.el),M&&be(M,g),(_e=C.props&&C.props.onVnodeUpdated)&&be(()=>Re(_e,$,C,Q),g)}else{let C;const{el:I,props:M}=u,{bm:$,m:Q,parent:B,root:_e,type:me}=c,Ne=Tt(u);tt(c,!1),$&&us($),!Ne&&(C=M&&M.onVnodeBeforeMount)&&Re(C,B,u),tt(c,!0);{_e.ce&&_e.ce._def.shadowRoot!==!1&&_e.ce._injectChildStyle(me);const De=c.subTree=sr(c);k(null,De,p,m,c,g,_),u.el=De.el}if(Q&&be(Q,g),!Ne&&(C=M&&M.onVnodeMounted)){const De=u;be(()=>Re(C,B,De),g)}(u.shapeFlag&256||B&&Tt(B.vnode)&&B.vnode.shapeFlag&256)&&c.a&&be(c.a,g),c.isMounted=!0,u=p=m=null}};c.scope.on();const y=c.effect=new sn(x);c.scope.off();const b=c.update=y.run.bind(y),A=c.job=y.runIfDirty.bind(y);A.i=c,A.id=c.uid,y.scheduler=()=>Os(A),tt(c,!0),b()},q=(c,u,p)=>{u.component=c;const m=c.vnode.props;c.vnode=u,c.next=null,so(c,u.props,m,p),oo(c,u.children,p),Pe(),Tn(c),Ee()},W=(c,u,p,m,g,_,S,x,y=!1)=>{const b=c&&c.children,A=c?c.shapeFlag:0,C=u.children,{patchFlag:I,shapeFlag:M}=u;if(I>0){if(I&128){D(b,C,p,m,g,_,S,x,y);return}else if(I&256){ge(b,C,p,m,g,_,S,x,y);return}}M&8?(A&16&&Je(b,g,_),C!==b&&f(p,C)):A&16?M&16?D(b,C,p,m,g,_,S,x,y):Je(b,g,_,!0):(A&8&&f(p,""),M&16&&ie(C,p,m,g,_,S,x,y))},ge=(c,u,p,m,g,_,S,x,y)=>{c=c||pt,u=u||pt;const b=c.length,A=u.length,C=Math.min(b,A);let I;for(I=0;I<C;I++){const M=u[I]=y?Ge(u[I]):ke(u[I]);k(c[I],M,p,null,g,_,S,x,y)}b>A?Je(c,g,_,!0,!1,C):ie(u,p,m,g,_,S,x,y,C)},D=(c,u,p,m,g,_,S,x,y)=>{let b=0;const A=u.length;let C=c.length-1,I=A-1;for(;b<=C&&b<=I;){const M=c[b],$=u[b]=y?Ge(u[b]):ke(u[b]);if(At(M,$))k(M,$,p,null,g,_,S,x,y);else break;b++}for(;b<=C&&b<=I;){const M=c[C],$=u[I]=y?Ge(u[I]):ke(u[I]);if(At(M,$))k(M,$,p,null,g,_,S,x,y);else break;C--,I--}if(b>C){if(b<=I){const M=I+1,$=M<A?u[M].el:m;for(;b<=I;)k(null,u[b]=y?Ge(u[b]):ke(u[b]),p,$,g,_,S,x,y),b++}}else if(b>I)for(;b<=C;)z(c[b],g,_,!0),b++;else{const M=b,$=b,Q=new Map;for(b=$;b<=I;b++){const ye=u[b]=y?Ge(u[b]):ke(u[b]);ye.key!=null&&Q.set(ye.key,b)}let B,_e=0;const me=I-$+1;let Ne=!1,De=0;const Rt=new Array(me);for(b=0;b<me;b++)Rt[b]=0;for(b=M;b<=C;b++){const ye=c[b];if(_e>=me){z(ye,g,_,!0);continue}let Le;if(ye.key!=null)Le=Q.get(ye.key);else for(B=$;B<=I;B++)if(Rt[B-$]===0&&At(ye,u[B])){Le=B;break}Le===void 0?z(ye,g,_,!0):(Rt[Le-$]=b+1,Le>=De?De=Le:Ne=!0,k(ye,u[Le],p,null,g,_,S,x,y),_e++)}const Ar=Ne?uo(Rt):pt;for(B=Ar.length-1,b=me-1;b>=0;b--){const ye=$+b,Le=u[ye],Or=u[ye+1],Mr=ye+1<A?Or.el||Or.placeholder:m;Rt[b]===0?k(null,Le,p,Mr,g,_,S,x,y):Ne&&(B<0||b!==Ar[B]?Y(Le,p,Mr,2):B--)}}},Y=(c,u,p,m,g=null)=>{const{el:_,type:S,transition:x,children:y,shapeFlag:b}=c;if(b&6){Y(c.component.subTree,u,p,m);return}if(b&128){c.suspense.move(u,p,m);return}if(b&64){S.move(c,u,p,kt);return}if(S===Ue){n(_,u,p);for(let C=0;C<y.length;C++)Y(y[C],u,p,m);n(c.anchor,u,p);return}if(S===js){U(c,u,p);return}if(m!==2&&b&1&&x)if(m===0)x.beforeEnter(_),n(_,u,p),be(()=>x.enter(_),g);else{const{leave:C,delayLeave:I,afterLeave:M}=x,$=()=>{c.ctx.isUnmounted?r(_):n(_,u,p)},Q=()=>{_._isLeaving&&_[ki](!0),C(_,()=>{$(),M&&M()})};I?I(_,$,Q):Q()}else n(_,u,p)},z=(c,u,p,m=!1,g=!1)=>{const{type:_,props:S,ref:x,children:y,dynamicChildren:b,shapeFlag:A,patchFlag:C,dirs:I,cacheIndex:M}=c;if(C===-2&&(g=!1),x!=null&&(Pe(),Ct(x,null,p,c,!0),Ee()),M!=null&&(u.renderCache[M]=void 0),A&256){u.ctx.deactivate(c);return}const $=A&1&&I,Q=!Tt(c);let B;if(Q&&(B=S&&S.onVnodeBeforeUnmount)&&Re(B,u,c),A&6)ns(c.component,p,m);else{if(A&128){c.suspense.unmount(p,m);return}$&&et(c,null,u,"beforeUnmount"),A&64?c.type.remove(c,u,p,kt,m):b&&!b.hasOnce&&(_!==Ue||C>0&&C&64)?Je(b,u,p,!1,!0):(_===Ue&&C&384||!g&&A&16)&&Je(y,u,p),m&&Fe(c)}(Q&&(B=S&&S.onVnodeUnmounted)||$)&&be(()=>{B&&Re(B,u,c),$&&et(c,null,u,"unmounted")},p)},Fe=c=>{const{type:u,el:p,anchor:m,transition:g}=c;if(u===Ue){ss(p,m);return}if(u===js){T(c);return}const _=()=>{r(p),g&&!g.persisted&&g.afterLeave&&g.afterLeave()};if(c.shapeFlag&1&&g&&!g.persisted){const{leave:S,delayLeave:x}=g,y=()=>S(p,_);x?x(c.el,_,y):y()}else _()},ss=(c,u)=>{let p;for(;c!==u;)p=v(c),r(c),c=p;r(u)},ns=(c,u,p)=>{const{bum:m,scope:g,job:_,subTree:S,um:x,m:y,a:b}=c;zn(y),zn(b),m&&us(m),g.stop(),_&&(_.flags|=8,z(S,c,u,p)),x&&be(x,u),be(()=>{c.isUnmounted=!0},u)},Je=(c,u,p,m=!1,g=!1,_=0)=>{for(let S=_;S<c.length;S++)z(c[S],u,p,m,g)},rs=c=>{if(c.shapeFlag&6)return rs(c.component.subTree);if(c.shapeFlag&128)return c.suspense.next();const u=v(c.anchor||c.el),p=u&&u[Oi];return p?v(p):u};let Js=!1;const Ir=(c,u,p)=>{c==null?u._vnode&&z(u._vnode,null,null,!0):k(u._vnode||null,c,u,null,null,null,p),u._vnode=c,Js||(Js=!0,Tn(),Pn(),Js=!1)},kt={p:k,um:z,m:Y,r:Fe,mt:Se,mc:ie,pc:W,pbc:L,n:rs,o:e};return{render:Ir,hydrate:void 0,createApp:Zi(Ir)}}function $s({type:e,props:t},s){return s==="svg"&&e==="foreignObject"||s==="mathml"&&e==="annotation-xml"&&t&&t.encoding&&t.encoding.includes("html")?void 0:s}function tt({effect:e,job:t},s){s?(e.flags|=32,t.flags|=4):(e.flags&=-33,t.flags&=-5)}function ao(e,t){return(!e||e&&!e.pendingBranch)&&t&&!t.persisted}function Qn(e,t,s=!1){const n=e.children,r=t.children;if(N(n)&&N(r))for(let i=0;i<n.length;i++){const o=n[i];let l=r[i];l.shapeFlag&1&&!l.dynamicChildren&&((l.patchFlag<=0||l.patchFlag===32)&&(l=r[i]=Ge(r[i]),l.el=o.el),!s&&l.patchFlag!==-2&&Qn(o,l)),l.type===zt&&l.patchFlag!==-1&&(l.el=o.el),l.type===ut&&!l.el&&(l.el=o.el)}}function uo(e){const t=e.slice(),s=[0];let n,r,i,o,l;const a=e.length;for(n=0;n<a;n++){const d=e[n];if(d!==0){if(r=s[s.length-1],e[r]<d){t[n]=r,s.push(n);continue}for(i=0,o=s.length-1;i<o;)l=i+o>>1,e[s[l]]<d?i=l+1:o=l;d<e[s[i]]&&(i>0&&(t[n]=s[i-1]),s[i]=n)}}for(i=s.length,o=s[i-1];i-- >0;)s[i]=o,o=t[o];return s}function Xn(e){const t=e.subTree.component;if(t)return t.asyncDep&&!t.asyncResolved?t:Xn(t)}function zn(e){if(e)for(let t=0;t<e.length;t++)e[t].flags|=8}const fo=Symbol.for("v-scx"),po=()=>Qt(fo);function It(e,t,s){return Zn(e,t,s)}function Zn(e,t,s=G){const{immediate:n,deep:r,flush:i,once:o}=s,l=le({},s),a=t&&n||!t&&i!=="post";let d;if(Mt){if(i==="sync"){const w=po();d=w.__watcherHandles||(w.__watcherHandles=[])}else if(!a){const w=()=>{};return w.stop=Te,w.resume=Te,w.pause=Te,w}}const f=fe;l.call=(w,P,k)=>Ae(w,f,P,k);let h=!1;i==="post"?l.scheduler=w=>{be(w,f&&f.suspense)}:i!=="sync"&&(h=!0,l.scheduler=(w,P)=>{P?w():Os(w)}),l.augmentJob=w=>{t&&(w.flags|=4),h&&(w.flags|=2,f&&(w.id=f.uid,w.i=f))};const v=yi(e,t,l);return Mt&&(d?d.push(v):a&&v()),v}function ho(e,t,s){const n=this.proxy,r=te(e)?e.includes(".")?er(n,e):()=>n[e]:e.bind(n,n);let i;F(t)?i=t:(i=t.handler,s=t);const o=Ot(this),l=Zn(r,i.bind(n),s);return o(),l}function er(e,t){const s=t.split(".");return()=>{let n=e;for(let r=0;r<s.length&&n;r++)n=n[s[r]];return n}}const go=(e,t)=>t==="modelValue"||t==="model-value"?e.modelModifiers:e[`${t}Modifiers`]||e[`${Ve(t)}Modifiers`]||e[`${Qe(t)}Modifiers`];function _o(e,t,...s){if(e.isUnmounted)return;const n=e.vnode.props||G;let r=s;const i=t.startsWith("update:"),o=i&&go(n,t.slice(7));o&&(o.trim&&(r=s.map(f=>te(f)?f.trim():f)),o.number&&(r=s.map($r)));let l,a=n[l=as(t)]||n[l=as(Ve(t))];!a&&i&&(a=n[l=as(Qe(t))]),a&&Ae(a,e,6,r);const d=n[l+"Once"];if(d){if(!e.emitted)e.emitted={};else if(e.emitted[l])return;e.emitted[l]=!0,Ae(d,e,6,r)}}const mo=new WeakMap;function tr(e,t,s=!1){const n=s?mo:t.emitsCache,r=n.get(e);if(r!==void 0)return r;const i=e.emits;let o={},l=!1;if(!F(e)){const a=d=>{const f=tr(d,t,!0);f&&(l=!0,le(o,f))};!s&&t.mixins.length&&t.mixins.forEach(a),e.extends&&a(e.extends),e.mixins&&e.mixins.forEach(a)}return!i&&!l?(ee(e)&&n.set(e,null),null):(N(i)?i.forEach(a=>o[a]=null):le(o,i),ee(e)&&n.set(e,o),o)}function Xt(e,t){return!e||!Ft(t)?!1:(t=t.slice(2).replace(/Once$/,""),V(e,t[0].toLowerCase()+t.slice(1))||V(e,Qe(t))||V(e,t))}function hl(){}function sr(e){const{type:t,vnode:s,proxy:n,withProxy:r,propsOptions:[i],slots:o,attrs:l,emit:a,render:d,renderCache:f,props:h,data:v,setupState:w,ctx:P,inheritAttrs:k}=e,Z=Kt(e);let R,O;try{if(s.shapeFlag&4){const T=r||n,K=T;R=ke(d.call(K,T,f,h,w,v,P)),O=l}else{const T=t;R=ke(T.length>1?T(h,{attrs:l,slots:o,emit:a}):T(h,null)),O=t.props?l:bo(l)}}catch(T){Wt(T,e,1),R=st(ut)}let U=R;if(O&&k!==!1){const T=Object.keys(O),{shapeFlag:K}=U;T.length&&K&7&&(i&&T.some(os)&&(O=vo(O,i)),U=ft(U,O,!1,!0))}return s.dirs&&(U=ft(U,null,!1,!0),U.dirs=U.dirs?U.dirs.concat(s.dirs):s.dirs),s.transition&&Ms(U,s.transition),R=U,Kt(Z),R}const bo=e=>{let t;for(const s in e)(s==="class"||s==="style"||Ft(s))&&((t||(t={}))[s]=e[s]);return t},vo=(e,t)=>{const s={};for(const n in e)(!os(n)||!(n.slice(9)in t))&&(s[n]=e[n]);return s};function yo(e,t,s){const{props:n,children:r,component:i}=e,{props:o,children:l,patchFlag:a}=t,d=i.emitsOptions;if(t.dirs||t.transition)return!0;if(s&&a>=0){if(a&1024)return!0;if(a&16)return n?nr(n,o,d):!!o;if(a&8){const f=t.dynamicProps;for(let h=0;h<f.length;h++){const v=f[h];if(o[v]!==n[v]&&!Xt(d,v))return!0}}}else return(r||l)&&(!l||!l.$stable)?!0:n===o?!1:n?o?nr(n,o,d):!0:!!o;return!1}function nr(e,t,s){const n=Object.keys(t);if(n.length!==Object.keys(e).length)return!0;for(let r=0;r<n.length;r++){const i=n[r];if(t[i]!==e[i]&&!Xt(s,i))return!0}return!1}function xo({vnode:e,parent:t},s){for(;t;){const n=t.subTree;if(n.suspense&&n.suspense.activeBranch===e&&(n.el=e.el),n===e)(e=t.vnode).el=s,t=t.parent;else break}}const rr=e=>e.__isSuspense;function wo(e,t){t&&t.pendingBranch?N(e)?t.effects.push(...e):t.effects.push(e):Ii(e)}const Ue=Symbol.for("v-fgt"),zt=Symbol.for("v-txt"),ut=Symbol.for("v-cmt"),js=Symbol.for("v-stc");let Ye=null,Us=1;function ir(e,t=!1){Us+=e,e<0&&Ye&&t&&(Ye.hasOnce=!0)}function or(e){return e?e.__v_isVNode===!0:!1}function At(e,t){return e.type===t.type&&e.key===t.key}const lr=({key:e})=>e!=null?e:null,Zt=({ref:e,ref_key:t,ref_for:s})=>(typeof e=="number"&&(e=""+e),e!=null?te(e)||ne(e)||F(e)?{i:Me,r:e,k:t,f:!!s}:e:null);function So(e,t=null,s=null,n=0,r=null,i=e===Ue?0:1,o=!1,l=!1){const a={__v_isVNode:!0,__v_skip:!0,type:e,props:t,key:t&&lr(t),ref:t&&Zt(t),scopeId:In,slotScopeIds:null,children:s,component:null,suspense:null,ssContent:null,ssFallback:null,dirs:null,transition:null,el:null,anchor:null,target:null,targetStart:null,targetAnchor:null,staticCount:0,shapeFlag:i,patchFlag:n,dynamicProps:r,dynamicChildren:null,appContext:null,ctx:Me};return l?(qs(a,s),i&128&&e.normalize(a)):s&&(a.shapeFlag|=te(s)?8:16),Us>0&&!o&&Ye&&(a.patchFlag>0||i&6)&&a.patchFlag!==32&&Ye.push(a),a}const st=Co;function Co(e,t=null,s=null,n=0,r=null,i=!1){if((!e||e===Bi)&&(e=ut),or(e)){const l=ft(e,t,!0);return s&&qs(l,s),Us>0&&!i&&Ye&&(l.shapeFlag&6?Ye[Ye.indexOf(e)]=l:Ye.push(l)),l.patchFlag=-2,l}if($o(e)&&(e=e.__vccOpts),t){t=To(t);let{class:l,style:a}=t;l&&!te(l)&&(t.class=ds(l)),ee(a)&&(Ps(a)&&!N(a)&&(a=le({},a)),t.style=fs(a))}const o=te(e)?1:rr(e)?128:Mi(e)?64:ee(e)?4:F(e)?2:0;return So(e,t,s,n,r,o,i,!0)}function To(e){return e?Ps(e)||Vn(e)?le({},e):e:null}function ft(e,t,s=!1,n=!1){const{props:r,ref:i,patchFlag:o,children:l,transition:a}=e,d=t?Eo(r||{},t):r,f={__v_isVNode:!0,__v_skip:!0,type:e.type,props:d,key:d&&lr(d),ref:t&&t.ref?s&&i?N(i)?i.concat(Zt(t)):[i,Zt(t)]:Zt(t):i,scopeId:e.scopeId,slotScopeIds:e.slotScopeIds,children:l,target:e.target,targetStart:e.targetStart,targetAnchor:e.targetAnchor,staticCount:e.staticCount,shapeFlag:e.shapeFlag,patchFlag:t&&e.type!==Ue?o===-1?16:o|16:o,dynamicProps:e.dynamicProps,dynamicChildren:e.dynamicChildren,appContext:e.appContext,dirs:e.dirs,transition:a,component:e.component,suspense:e.suspense,ssContent:e.ssContent&&ft(e.ssContent),ssFallback:e.ssFallback&&ft(e.ssFallback),placeholder:e.placeholder,el:e.el,anchor:e.anchor,ctx:e.ctx,ce:e.ce};return a&&n&&Ms(f,a.clone(f)),f}function Po(e=" ",t=0){return st(zt,null,e,t)}function ke(e){return e==null||typeof e=="boolean"?st(ut):N(e)?st(Ue,null,e.slice()):or(e)?Ge(e):st(zt,null,String(e))}function Ge(e){return e.el===null&&e.patchFlag!==-1||e.memo?e:ft(e)}function qs(e,t){let s=0;const{shapeFlag:n}=e;if(t==null)t=null;else if(N(t))s=16;else if(typeof t=="object")if(n&65){const r=t.default;r&&(r._c&&(r._d=!1),qs(e,r()),r._c&&(r._d=!0));return}else{s=32;const r=t._;!r&&!Vn(t)?t._ctx=Me:r===3&&Me&&(Me.slots._===1?t._=1:(t._=2,e.patchFlag|=1024))}else F(t)?(t={default:t,_ctx:Me},s=32):(t=String(t),n&64?(s=16,t=[Po(t)]):s=8);e.children=t,e.shapeFlag|=s}function Eo(...e){const t={};for(let s=0;s<e.length;s++){const n=e[s];for(const r in n)if(r==="class")t.class!==n.class&&(t.class=ds([t.class,n.class]));else if(r==="style")t.style=fs([t.style,n.style]);else if(Ft(r)){const i=t[r],o=n[r];o&&i!==o&&!(N(i)&&i.includes(o))&&(t[r]=i?[].concat(i,o):o)}else r!==""&&(t[r]=n[r])}return t}function Re(e,t,s,n=null){Ae(e,t,7,[s,n])}const Io=jn();let Ao=0;function Oo(e,t,s){const n=e.type,r=(t?t.appContext:e.appContext)||Io,i={uid:Ao++,vnode:e,type:n,parent:t,appContext:r,root:null,next:null,subTree:null,effect:null,update:null,job:null,scope:new Br(!0),render:null,proxy:null,exposed:null,exposeProxy:null,withProxy:null,provides:t?t.provides:Object.create(r.provides),ids:t?t.ids:["",0,0],accessCache:null,renderCache:[],components:null,directives:null,propsOptions:Bn(n,r),emitsOptions:tr(n,r),emit:null,emitted:null,propsDefaults:G,inheritAttrs:n.inheritAttrs,ctx:G,data:G,props:G,attrs:G,slots:G,refs:G,setupState:G,setupContext:null,suspense:s,suspenseId:s?s.pendingId:0,asyncDep:null,asyncResolved:!1,isMounted:!1,isUnmounted:!1,isDeactivated:!1,bc:null,c:null,bm:null,m:null,bu:null,u:null,um:null,bum:null,da:null,a:null,rtg:null,rtc:null,ec:null,sp:null};return i.ctx={_:i},i.root=t?t.root:i,i.emit=_o.bind(null,i),e.ce&&e.ce(i),i}let fe=null;const Mo=()=>fe||Me;let es,Vs;{const e=Lt(),t=(s,n)=>{let r;return(r=e[s])||(r=e[s]=[]),r.push(n),i=>{r.length>1?r.forEach(o=>o(i)):r[0](i)}};es=t("__VUE_INSTANCE_SETTERS__",s=>fe=s),Vs=t("__VUE_SSR_SETTERS__",s=>Mt=s)}const Ot=e=>{const t=fe;return es(e),e.scope.on(),()=>{e.scope.off(),es(t)}},cr=()=>{fe&&fe.scope.off(),es(null)};function ar(e){return e.vnode.shapeFlag&4}let Mt=!1;function ko(e,t=!1,s=!1){t&&Vs(t);const{props:n,children:r}=e.vnode,i=ar(e);to(e,n,i,t),io(e,r,s||t);const o=i?Ro(e,t):void 0;return t&&Vs(!1),o}function Ro(e,t){const s=e.type;e.accessCache=Object.create(null),e.proxy=new Proxy(e.ctx,Ki);const{setup:n}=s;if(n){Pe();const r=e.setupContext=n.length>1?No(e):null,i=Ot(e),o=ot(n,e,0,[e.props,r]),l=Xs(o);if(Ee(),i(),(l||e.sp)&&!Tt(e)&&An(e),l){if(o.then(cr,cr),t)return o.then(a=>{ur(e,a)}).catch(a=>{Wt(a,e,0)});e.asyncDep=o}else ur(e,o)}else fr(e)}function ur(e,t,s){F(t)?e.type.__ssrInlineRender?e.ssrRender=t:e.render=t:ee(t)&&(e.setupState=xn(t)),fr(e)}function fr(e,t,s){const n=e.type;e.render||(e.render=n.render||Te);{const r=Ot(e);Pe();try{Yi(e)}finally{Ee(),r()}}}const Fo={get(e,t){return oe(e,"get",""),e[t]}};function No(e){const t=s=>{e.exposed=s||{}};return{attrs:new Proxy(e.attrs,Fo),slots:e.slots,emit:e.emit,expose:t}}function Ws(e){return e.exposed?e.exposeProxy||(e.exposeProxy=new Proxy(xn(di(e.exposed)),{get(t,s){if(s in t)return t[s];if(s in Pt)return Pt[s](e)},has(t,s){return s in t||s in Pt}})):e.proxy}const Do=/(?:^|[-_])\w/g,Lo=e=>e.replace(Do,t=>t.toUpperCase()).replace(/[-_]/g,"");function Ho(e,t=!0){return F(e)?e.displayName||e.name:e.name||t&&e.__name}function dr(e,t,s=!1){let n=Ho(t);if(!n&&t.__file){const r=t.__file.match(/([^/\\]+)\.\w+$/);r&&(n=r[1])}if(!n&&e&&e.parent){const r=i=>{for(const o in i)if(i[o]===t)return o};n=r(e.components||e.parent.type.components)||r(e.appContext.components)}return n?Lo(n):s?"App":"Anonymous"}function $o(e){return F(e)&&"__vccOpts"in e}const se=(e,t)=>bi(e,t,Mt),jo="3.5.22";/**
* @vue/runtime-dom v3.5.22
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/let Bs;const pr=typeof window!="undefined"&&window.trustedTypes;if(pr)try{Bs=pr.createPolicy("vue",{createHTML:e=>e})}catch(e){}const hr=Bs?e=>Bs.createHTML(e):e=>e,Uo="http://www.w3.org/2000/svg",qo="http://www.w3.org/1998/Math/MathML",qe=typeof document!="undefined"?document:null,gr=qe&&qe.createElement("template"),Vo={insert:(e,t,s)=>{t.insertBefore(e,s||null)},remove:e=>{const t=e.parentNode;t&&t.removeChild(e)},createElement:(e,t,s,n)=>{const r=t==="svg"?qe.createElementNS(Uo,e):t==="mathml"?qe.createElementNS(qo,e):s?qe.createElement(e,{is:s}):qe.createElement(e);return e==="select"&&n&&n.multiple!=null&&r.setAttribute("multiple",n.multiple),r},createText:e=>qe.createTextNode(e),createComment:e=>qe.createComment(e),setText:(e,t)=>{e.nodeValue=t},setElementText:(e,t)=>{e.textContent=t},parentNode:e=>e.parentNode,nextSibling:e=>e.nextSibling,querySelector:e=>qe.querySelector(e),setScopeId(e,t){e.setAttribute(t,"")},insertStaticContent(e,t,s,n,r,i){const o=s?s.previousSibling:t.lastChild;if(r&&(r===i||r.nextSibling))for(;t.insertBefore(r.cloneNode(!0),s),!(r===i||!(r=r.nextSibling)););else{gr.innerHTML=hr(n==="svg"?`<svg>${e}</svg>`:n==="mathml"?`<math>${e}</math>`:e);const l=gr.content;if(n==="svg"||n==="mathml"){const a=l.firstChild;for(;a.firstChild;)l.appendChild(a.firstChild);l.removeChild(a)}t.insertBefore(l,s)}return[o?o.nextSibling:t.firstChild,s?s.previousSibling:t.lastChild]}},Wo=Symbol("_vtc");function Bo(e,t,s){const n=e[Wo];n&&(t=(t?[t,...n]:[...n]).join(" ")),t==null?e.removeAttribute("class"):s?e.setAttribute("class",t):e.className=t}const _r=Symbol("_vod"),Ko=Symbol("_vsh"),Yo=Symbol(""),Go=/(?:^|;)\s*display\s*:/;function Jo(e,t,s){const n=e.style,r=te(s);let i=!1;if(s&&!r){if(t)if(te(t))for(const o of t.split(";")){const l=o.slice(0,o.indexOf(":")).trim();s[l]==null&&ts(n,l,"")}else for(const o in t)s[o]==null&&ts(n,o,"");for(const o in s)o==="display"&&(i=!0),ts(n,o,s[o])}else if(r){if(t!==s){const o=n[Yo];o&&(s+=";"+o),n.cssText=s,i=Go.test(s)}}else t&&e.removeAttribute("style");_r in e&&(e[_r]=i?n.display:"",e[Ko]&&(n.display="none"))}const mr=/\s*!important$/;function ts(e,t,s){if(N(s))s.forEach(n=>ts(e,t,n));else if(s==null&&(s=""),t.startsWith("--"))e.setProperty(t,s);else{const n=Qo(e,t);mr.test(s)?e.setProperty(Qe(n),s.replace(mr,""),"important"):e[n]=s}}const br=["Webkit","Moz","ms"],Ks={};function Qo(e,t){const s=Ks[t];if(s)return s;let n=Ve(t);if(n!=="filter"&&n in e)return Ks[t]=n;n=zs(n);for(let r=0;r<br.length;r++){const i=br[r]+n;if(i in e)return Ks[t]=i}return t}const vr="http://www.w3.org/1999/xlink";function yr(e,t,s,n,r,i=Wr(t)){n&&t.startsWith("xlink:")?s==null?e.removeAttributeNS(vr,t.slice(6,t.length)):e.setAttributeNS(vr,t,s):s==null||i&&!tn(s)?e.removeAttribute(t):e.setAttribute(t,i?"":rt(s)?String(s):s)}function xr(e,t,s,n,r){if(t==="innerHTML"||t==="textContent"){s!=null&&(e[t]=t==="innerHTML"?hr(s):s);return}const i=e.tagName;if(t==="value"&&i!=="PROGRESS"&&!i.includes("-")){const l=i==="OPTION"?e.getAttribute("value")||"":e.value,a=s==null?e.type==="checkbox"?"on":"":String(s);(l!==a||!("_value"in e))&&(e.value=a),s==null&&e.removeAttribute(t),e._value=s;return}let o=!1;if(s===""||s==null){const l=typeof e[t];l==="boolean"?s=tn(s):s==null&&l==="string"?(s="",o=!0):l==="number"&&(s=0,o=!0)}try{e[t]=s}catch(l){}o&&e.removeAttribute(r||t)}function Xo(e,t,s,n){e.addEventListener(t,s,n)}function zo(e,t,s,n){e.removeEventListener(t,s,n)}const wr=Symbol("_vei");function Zo(e,t,s,n,r=null){const i=e[wr]||(e[wr]={}),o=i[t];if(n&&o)o.value=n;else{const[l,a]=el(t);if(n){const d=i[t]=nl(n,r);Xo(e,l,d,a)}else o&&(zo(e,l,o,a),i[t]=void 0)}}const Sr=/(?:Once|Passive|Capture)$/;function el(e){let t;if(Sr.test(e)){t={};let n;for(;n=e.match(Sr);)e=e.slice(0,e.length-n[0].length),t[n[0].toLowerCase()]=!0}return[e[2]===":"?e.slice(3):Qe(e.slice(2)),t]}let Ys=0;const tl=Promise.resolve(),sl=()=>Ys||(tl.then(()=>Ys=0),Ys=Date.now());function nl(e,t){const s=n=>{if(!n._vts)n._vts=Date.now();else if(n._vts<=s.attached)return;Ae(rl(n,s.value),t,5,[n])};return s.value=e,s.attached=sl(),s}function rl(e,t){if(N(t)){const s=e.stopImmediatePropagation;return e.stopImmediatePropagation=()=>{s.call(e),e._stopped=!0},t.map(n=>r=>!r._stopped&&n&&n(r))}else return t}const Cr=e=>e.charCodeAt(0)===111&&e.charCodeAt(1)===110&&e.charCodeAt(2)>96&&e.charCodeAt(2)<123,il=(e,t,s,n,r,i)=>{const o=r==="svg";t==="class"?Bo(e,n,o):t==="style"?Jo(e,s,n):Ft(t)?os(t)||Zo(e,t,s,n,i):(t[0]==="."?(t=t.slice(1),!0):t[0]==="^"?(t=t.slice(1),!1):ol(e,t,n,o))?(xr(e,t,n),!e.tagName.includes("-")&&(t==="value"||t==="checked"||t==="selected")&&yr(e,t,n,o,i,t!=="value")):e._isVueCE&&(/[A-Z]/.test(t)||!te(n))?xr(e,Ve(t),n,i,t):(t==="true-value"?e._trueValue=n:t==="false-value"&&(e._falseValue=n),yr(e,t,n,o))};function ol(e,t,s,n){if(n)return!!(t==="innerHTML"||t==="textContent"||t in e&&Cr(t)&&F(s));if(t==="spellcheck"||t==="draggable"||t==="translate"||t==="autocorrect"||t==="form"||t==="list"&&e.tagName==="INPUT"||t==="type"&&e.tagName==="TEXTAREA")return!1;if(t==="width"||t==="height"){const r=e.tagName;if(r==="IMG"||r==="VIDEO"||r==="CANVAS"||r==="SOURCE")return!1}return Cr(t)&&te(s)?!1:t in e}const ll=le({patchProp:il},Vo);let Tr;function cl(){return Tr||(Tr=lo(ll))}const dt=(...e)=>{const t=cl().createApp(...e),{mount:s}=t;return t.mount=n=>{const r=ul(n);if(!r)return;const i=t._component;!F(i)&&!i.render&&!i.template&&(i.template=r.innerHTML),r.nodeType===1&&(r.textContent="");const o=s(r,!1,al(r));return r instanceof Element&&(r.removeAttribute("v-cloak"),r.setAttribute("data-v-app","")),o},t};function al(e){if(e instanceof SVGElement)return"svg";if(typeof MathMLElement=="function"&&e instanceof MathMLElement)return"mathml"}function ul(e){return te(e)?document.querySelector(e):e}const Gs=(e,t)=>{const s=(e||0)/100;try{return new Intl.NumberFormat(void 0,{style:"currency",currency:t||"USD",minimumFractionDigits:2}).format(s)}catch(n){return`${t?`${t} `:""}${s.toFixed(2)}`}},nt=(e,t={})=>{if(!e)return t;try{return JSON.parse(e)}catch(s){return console.warn("[NXP Easy Cart] Failed to parse island payload",s),t}},Pr={product:e=>{var w,P,k,Z,R,O,U,T,K,de,re,ie,ve;const t=nt(e.dataset.nxpProduct,{}),s=t.product||{},r=(Array.isArray(t.variants)?t.variants:[]).map(L=>({...L,id:Number(L.id||0),stock:L.stock===null||L.stock===void 0?null:Number(L.stock)})).filter(L=>Number.isFinite(L.id)&&L.id>0),i={add_to_cart:((w=t.labels)==null?void 0:w.add_to_cart)||"Add to cart",select_variant:((P=t.labels)==null?void 0:P.select_variant)||"Select a variant",out_of_stock:((k=t.labels)==null?void 0:k.out_of_stock)||"Out of stock",added:((Z=t.labels)==null?void 0:Z.added)||"Added to cart",view_cart:((R=t.labels)==null?void 0:R.view_cart)||"View cart",qty_label:((O=t.labels)==null?void 0:O.qty_label)||"Quantity",error_generic:((U=t.labels)==null?void 0:U.error_generic)||"We couldn't add this item to your cart. Please try again.",variants_heading:((T=t.labels)==null?void 0:T.variants_heading)||"Variants",variant_sku:((K=t.labels)==null?void 0:K.variant_sku)||"SKU",variant_price:((de=t.labels)==null?void 0:de.variant_price)||"Price",variant_stock:((re=t.labels)==null?void 0:re.variant_stock)||"Stock",variant_options:((ie=t.labels)==null?void 0:ie.variant_options)||"Options",variant_none:((ve=t.labels)==null?void 0:ve.variant_none)||"—"},o=t.endpoints||{},l=t.links||{},a=t.token||"",d=Array.isArray(s.images)?s.images:[],f=d.length?d[0]:"",h=t.primary_alt||s.title||i.add_to_cart;e.innerHTML="",dt({template:`
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
    `,setup(){const L=`nxp-ec-variant-${s.id||"0"}`,pe=`nxp-ec-qty-${s.id||"0"}`,E=Ie({variantId:r.length===1?r[0].id:null,qty:1,loading:!1,success:!1,successMessage:"",error:""}),X=se(()=>r.length?E.variantId?r.find(D=>D.id===E.variantId)||null:r.length===1?r[0]:null:null),Se=se(()=>{const D=X.value;if(!D||D.stock===null||D.stock===void 0||!Number.isFinite(D.stock))return;const Y=Number(D.stock);if(!(!Number.isFinite(Y)||Y<=0))return Y}),Ce=D=>{let Y=Number(D);(!Number.isFinite(Y)||Y<1)&&(Y=1);const z=Se.value;return Number.isFinite(z)&&(Y=Math.min(Y,z)),Y};It(()=>E.qty,D=>{const Y=Ce(D);Y!==D&&(E.qty=Y)}),It(()=>E.variantId,()=>{E.error="",E.success=!1,E.successMessage="";const D=Ce(E.qty);D!==E.qty&&(E.qty=D)});const H=se(()=>{var D;return X.value&&X.value.price_label?X.value.price_label:((D=s.price)==null?void 0:D.label)||""}),q=se(()=>{const D=X.value;return!D||D.stock===null||D.stock===void 0?!1:Number(D.stock)<=0}),W=se(()=>!!(E.loading||!r.length||!X.value||q.value));return{product:s,variants:r,labels:i,links:l,primaryImage:f,primaryAlt:h,state:E,add:async()=>{var Y;if(E.error="",E.success=!1,E.successMessage="",!o.add){E.error=i.error_generic;return}const D=X.value;if(r.length&&!D){E.error=i.select_variant;return}if(q.value){E.error=i.out_of_stock;return}E.loading=!0;try{const z=new FormData;a&&z.append(a,"1"),z.append("product_id",String(s.id||"")),z.append("qty",String(Ce(E.qty))),D&&z.append("variant_id",String(D.id));let Fe=null;const ss=await fetch(o.add,{method:"POST",body:z,headers:{Accept:"application/json"}});try{Fe=await ss.json()}catch(Je){}if(!ss.ok||!Fe||Fe.success===!1){const Je=Fe&&Fe.message||i.error_generic;throw new Error(Je)}const ns=((Y=Fe.data)==null?void 0:Y.cart)||null;E.success=!0,E.successMessage=Fe.message||i.added,ns&&window.dispatchEvent(new CustomEvent("nxp-cart:updated",{detail:ns}))}catch(z){E.error=z&&z.message||i.error_generic}finally{E.loading=!1}},displayPrice:H,isDisabled:W,isOutOfStock:q,maxQty:Se,variantSelectId:L,qtyInputId:pe}}}).mount(e)},category:e=>{const t=nt(e.dataset.nxpCategory,{}),s=nt(e.dataset.nxpProducts,[]),n=e.dataset.nxpSearch||"";e.innerHTML="",dt({template:`
      <div class="nxp-ec-category" v-cloak>
        <header class="nxp-ec-category__header">
          <h1 class="nxp-ec-category__title">{{ title }}</h1>
          <div class="nxp-ec-category__search">
            <input
              type="search"
              class="nxp-ec-admin-search"
              v-model="search"
              :placeholder="searchPlaceholder"
            />
          </div>
        </header>

        <p v-if="filteredProducts.length === 0" class="nxp-ec-category__empty">
          {{ emptyCopy }}
        </p>

        <div v-else class="nxp-ec-category__grid">
          <article
            v-for="product in filteredProducts"
            :key="product.id"
            class="nxp-ec-product-card"
          >
            <figure v-if="product.images && product.images.length" class="nxp-ec-product-card__media">
              <img :src="product.images[0]" :alt="product.title" loading="lazy" />
            </figure>
            <div class="nxp-ec-product-card__body">
              <h2 class="nxp-ec-product-card__title">
                <a :href="product.link">{{ product.title }}</a>
              </h2>
              <p v-if="product.short_desc" class="nxp-ec-product-card__intro">
                {{ product.short_desc }}
              </p>
              <a class="nxp-ec-btn nxp-ec-btn--ghost" :href="product.link">
                {{ viewCopy }}
              </a>
            </div>
          </article>
        </div>
      </div>
    `,setup(){const i=(t==null?void 0:t.title)||"Products",o=Is(n),l=se(()=>{if(!o.value)return s;const a=o.value.toLowerCase();return s.filter(d=>`${d.title} ${d.short_desc||""}`.toLowerCase().includes(a))});return{title:i,search:o,filteredProducts:l,searchPlaceholder:"Search products",emptyCopy:"No products found in this category yet.",viewCopy:"View product"}}}).mount(e)},landing:e=>{var P,k;const t=nt(e.dataset.nxpLanding,{}),s=t.hero||{},n=t.search||{},r=Array.isArray(t.categories)?t.categories:[],i=Array.isArray(t.sections)?t.sections:[],o=t.labels||{},l=t.trust||{},a=n.action||"index.php?option=com_nxpeasycart&view=category",d=n.placeholder||"Search for shoes, laptops, gifts…",f=((P=s==null?void 0:s.cta)==null?void 0:P.label)||"Shop Best Sellers",h=((k=s==null?void 0:s.cta)==null?void 0:k.link)||a,v={search_label:o.search_label||"Search the catalogue",search_button:o.search_button||"Search",view_all:o.view_all||"View all",view_product:o.view_product||"View product",categories_aria:o.categories_aria||"Browse categories"};e.innerHTML="",dt({template:`
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
    `,setup(){var ie,ve;const Z={eyebrow:s.eyebrow||"",title:s.title||"Shop",subtitle:s.subtitle||""},R={label:((ie=s==null?void 0:s.cta)==null?void 0:ie.label)||f,link:((ve=s==null?void 0:s.cta)==null?void 0:ve.link)||h},O=n.action||a,U=n.placeholder||d,T=i.filter(L=>Array.isArray(L.items)&&L.items.length),K=se(()=>T.map(L=>({key:L.key||L.title,title:L.title||"",items:L.items.slice(0,12)}))),de=Is("");return{hero:Z,cta:R,term:de,submitSearch:()=>{const L=n.action||a,pe=de.value.trim();try{const E=new URL(L,window.location.origin);pe?E.searchParams.set("q",pe):E.searchParams.delete("q"),window.location.href=E.toString()}catch(E){if(pe){const X=L.includes("?")?"&":"?";window.location.href=`${L}${X}q=${encodeURIComponent(pe)}`;return}window.location.href=L}},searchPlaceholder:U,searchAction:O,labels:v,categoryTiles:r,visibleSections:K,trust:typeof l.text=="string"?{text:l.text}:{text:""}}}}).mount(e)},cart:e=>{const t=nt(e.dataset.nxpCart,{items:[],summary:{}});e.innerHTML="",dt({template:`
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
    `,setup(){var d,f,h;const n=Ie(t.items||[]),r=((d=t.summary)==null?void 0:d.currency)||"USD",i=Ie({subtotal_cents:((f=t.summary)==null?void 0:f.subtotal_cents)||0,total_cents:((h=t.summary)==null?void 0:h.total_cents)||0}),o=()=>{const v=n.reduce((w,P)=>w+(P.total_cents||0),0);i.subtotal_cents=v,i.total_cents=v};return{items:n,summary:i,remove:v=>{const w=n.indexOf(v);w>=0&&(n.splice(w,1),o())},updateQty:(v,w)=>{const P=Math.max(1,parseInt(w,10)||1);v.qty=P,v.total_cents=P*(v.unit_price_cents||0),o()},format:v=>Gs(v,r)}}}).mount(e)},"cart-summary":e=>{const t=nt(e.dataset.nxpCartSummary,{}),s=t.labels||{},n=t.links||{};e.innerHTML="",dt({template:`
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
    `,setup(){const i=Ie({count:Number(t.count||0),total_cents:Number(t.total_cents||0),currency:t.currency||"USD"}),o=se(()=>i.count===1?s.items_single||"1 item":(s.items_plural||"%d items").replace("%d",i.count)),l=se(()=>Gs(i.total_cents,i.currency||"USD")),a=d=>{var v,w;if(!d)return;const f=Array.isArray(d.items)?d.items:[];let h=0;f.forEach(P=>{h+=Number(P.qty||0)}),i.count=h,i.total_cents=Number(((v=d.summary)==null?void 0:v.total_cents)||i.total_cents),i.currency=((w=d.summary)==null?void 0:w.currency)||i.currency||"USD"};return window.addEventListener("nxp-cart:updated",d=>{a(d.detail)}),{state:i,labels:s,links:n,countLabel:o,totalLabel:l}}}).mount(e)},checkout:e=>{const t=nt(e.dataset.nxpCheckout,{}),s=t.cart||{items:[],summary:{}},n=t.shipping_rules||[];t.tax_rates;const r=t.settings||{},i=t.payments||{},o=t.endpoints||{},l=t.token||"";e.innerHTML="",dt({template:`
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
    `,setup(){var re,ie,ve,L,pe;const d=Ie((s.items||[]).map(E=>({...E}))),f=((re=s.summary)==null?void 0:re.currency)||r.base_currency||"USD",h=n.map((E,X)=>({...E,price_cents:E.price_cents||0,default:X===0})),v=(E,X=[])=>X.every(Se=>{var H;const Ce=(H=E[Se])!=null?H:"";return String(Ce).trim()!==""}),w=[];v((ie=i.stripe)!=null?ie:{},["publishable_key","secret_key"])&&w.push({id:"stripe",label:"Card (Stripe)"}),v((ve=i.paypal)!=null?ve:{},["client_id","client_secret"])&&w.push({id:"paypal",label:"PayPal"});const P=w,k=Is(((L=P[0])==null?void 0:L.id)||""),Z=P.length>0&&!!o.payment,R=Ie({email:"",billing:{first_name:"",last_name:"",address_line1:"",city:"",postcode:"",country:""},shipping_rule_id:((pe=h[0])==null?void 0:pe.id)||null}),O=Ie({loading:!1,error:"",success:!1,orderNumber:"",orderUrl:"index.php?option=com_nxpeasycart&view=order"}),U=se(()=>d.reduce((E,X)=>E+(X.total_cents||0),0)),T=se(()=>{const E=h.find(X=>String(X.id)===String(R.shipping_rule_id));return E?E.price_cents:0}),K=se(()=>U.value+T.value);return{model:R,cartItems:d,shippingRules:h,subtotal:U,selectedShippingCost:T,total:K,submit:async()=>{var Se,Ce;if(O.error="",d.length===0){O.error="Your cart is empty.";return}O.loading=!0;const E=k.value||((Se=P[0])==null?void 0:Se.id)||"",X={email:R.email,billing:R.billing,shipping_rule_id:R.shipping_rule_id,items:d.map(H=>({sku:H.sku,qty:H.qty,product_id:H.product_id,variant_id:H.variant_id,unit_price_cents:H.unit_price_cents,total_cents:H.total_cents,currency:f,title:H.title})),currency:f,totals:{subtotal_cents:U.value,shipping_cents:T.value,total_cents:K.value},gateway:E};try{if(Z&&E){const ge=await fetch(o.payment,{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-Token":l,"X-Requested-With":"XMLHttpRequest"},body:JSON.stringify(X),credentials:"same-origin"});if(!ge.ok){const z=`Checkout failed (${ge.status})`;throw new Error(z)}const D=await ge.json(),Y=(Ce=D==null?void 0:D.checkout)==null?void 0:Ce.url;if(!Y)throw new Error("Missing checkout URL from gateway.");window.location.href=Y;return}if(!o.checkout)throw new Error("Checkout endpoint unavailable.");const H=await fetch(o.checkout,{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-Token":l,"X-Requested-With":"XMLHttpRequest"},body:JSON.stringify(X),credentials:"same-origin"});if(!H.ok){const ge=`Checkout failed (${H.status})`;throw new Error(ge)}const q=await H.json(),W=(q==null?void 0:q.order)||{};O.success=!0,O.orderNumber=W.order_no||"",O.orderUrl=`index.php?option=com_nxpeasycart&view=order&no=${encodeURIComponent(O.orderNumber)}`}catch(H){O.error=H.message||"Unable to complete checkout right now."}finally{O.loading=!1}},loading:se(()=>O.loading),error:se(()=>O.error),success:se(()=>O.success),orderNumber:se(()=>O.orderNumber),orderUrl:se(()=>O.orderUrl),formatMoney:E=>Gs(E,f),gateways:P,selectedGateway:k}}}).mount(e)}},Er=()=>{document.querySelectorAll("[data-nxp-island]").forEach(e=>{const t=e.dataset.nxpIsland;!t||!Pr[t]||Pr[t](e)})};document.readyState==="loading"?document.addEventListener("DOMContentLoaded",Er):Er()})();
