import{c as E,r as s,j as k}from"./index-B1lwkVPc.js";import{M as T,i as W,u as b,P as X,b as q,c as A,L as H}from"./api-BReEPY4M.js";/**
 * @license lucide-react v0.453.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const J=E("ChevronDown",[["path",{d:"m6 9 6 6 6-6",key:"qrunsl"}]]);/**
 * @license lucide-react v0.453.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const N=E("Moon",[["path",{d:"M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z",key:"a7tn18"}]]);/**
 * @license lucide-react v0.453.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const O=E("Sun",[["circle",{cx:"12",cy:"12",r:"4",key:"4exip2"}],["path",{d:"M12 2v2",key:"tus03m"}],["path",{d:"M12 20v2",key:"1lh1kg"}],["path",{d:"m4.93 4.93 1.41 1.41",key:"149t6j"}],["path",{d:"m17.66 17.66 1.41 1.41",key:"ptbguv"}],["path",{d:"M2 12h2",key:"1t8f8n"}],["path",{d:"M20 12h2",key:"1q8mjw"}],["path",{d:"m6.34 17.66-1.41 1.41",key:"1m8zz5"}],["path",{d:"m19.07 4.93-1.41 1.41",key:"1shlcs"}]]);/**
 * @license lucide-react v0.453.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Q=E("X",[["path",{d:"M18 6 6 18",key:"1bl5f8"}],["path",{d:"m6 6 12 12",key:"d8bk6v"}]]);function L(e,o){if(typeof e=="function")return e(o);e!=null&&(e.current=o)}function K(...e){return o=>{let t=!1;const r=e.map(i=>{const n=L(i,o);return!t&&typeof n=="function"&&(t=!0),n});if(t)return()=>{for(let i=0;i<r.length;i++){const n=r[i];typeof n=="function"?n():L(e[i],null)}}}}function U(...e){return s.useCallback(K(...e),e)}class B extends s.Component{getSnapshotBeforeUpdate(o){const t=this.props.childRef.current;if(t&&o.isPresent&&!this.props.isPresent){const r=t.offsetParent,i=W(r)&&r.offsetWidth||0,n=this.props.sizeRef.current;n.height=t.offsetHeight||0,n.width=t.offsetWidth||0,n.top=t.offsetTop,n.left=t.offsetLeft,n.right=i-n.width-n.left}return null}componentDidUpdate(){}render(){return this.props.children}}function F({children:e,isPresent:o,anchorX:t,root:r}){const i=s.useId(),n=s.useRef(null),p=s.useRef({width:0,height:0,top:0,left:0,right:0}),{nonce:y}=s.useContext(T),M=U(n,e?.ref);return s.useInsertionEffect(()=>{const{width:c,height:x,top:u,left:a,right:d}=p.current;if(o||!n.current||!c||!x)return;const m=t==="left"?`left: ${a}`:`right: ${d}`;n.current.dataset.motionPopId=i;const l=document.createElement("style");y&&(l.nonce=y);const R=r??document.head;return R.appendChild(l),l.sheet&&l.sheet.insertRule(`
          [data-motion-pop-id="${i}"] {
            position: absolute !important;
            width: ${c}px !important;
            height: ${x}px !important;
            ${m}px !important;
            top: ${u}px !important;
          }
        `),()=>{R.contains(l)&&R.removeChild(l)}},[o]),k.jsx(B,{isPresent:o,childRef:n,sizeRef:p,children:s.cloneElement(e,{ref:M})})}const G=({children:e,initial:o,isPresent:t,onExitComplete:r,custom:i,presenceAffectsLayout:n,mode:p,anchorX:y,root:M})=>{const c=b(V),x=s.useId();let u=!0,a=s.useMemo(()=>(u=!1,{id:x,initial:o,isPresent:t,custom:i,onExitComplete:d=>{c.set(d,!0);for(const m of c.values())if(!m)return;r&&r()},register:d=>(c.set(d,!1),()=>c.delete(d))}),[t,c,r]);return n&&u&&(a={...a}),s.useMemo(()=>{c.forEach((d,m)=>c.set(m,!1))},[t]),s.useEffect(()=>{!t&&!c.size&&r&&r()},[t]),p==="popLayout"&&(e=k.jsx(F,{isPresent:t,anchorX:y,root:M,children:e})),k.jsx(X.Provider,{value:a,children:e})};function V(){return new Map}const v=e=>e.key||"";function $(e){const o=[];return s.Children.forEach(e,t=>{s.isValidElement(t)&&o.push(t)}),o}const Y=({children:e,custom:o,initial:t=!0,onExitComplete:r,presenceAffectsLayout:i=!0,mode:n="sync",propagate:p=!1,anchorX:y="left",root:M})=>{const[c,x]=q(p),u=s.useMemo(()=>$(e),[e]),a=p&&!c?[]:u.map(v),d=s.useRef(!0),m=s.useRef(u),l=b(()=>new Map),[R,I]=s.useState(u),[C,j]=s.useState(u);A(()=>{d.current=!1,m.current=u;for(let h=0;h<C.length;h++){const f=v(C[h]);a.includes(f)?l.delete(f):l.get(f)!==!0&&l.set(f,!1)}},[C,a.length,a.join("-")]);const w=[];if(u!==R){let h=[...u];for(let f=0;f<C.length;f++){const g=C[f],P=v(g);a.includes(P)||(h.splice(f,0,g),w.push(g))}return n==="wait"&&w.length&&(h=w),j($(h)),I(u),null}const{forceRender:S}=s.useContext(H);return k.jsx(k.Fragment,{children:C.map(h=>{const f=v(h),g=p&&!c?!1:u===C||a.includes(f),P=()=>{if(l.has(f))l.set(f,!0);else return;let z=!0;l.forEach(D=>{D||(z=!1)}),z&&(S?.(),j(m.current),p&&x?.(),r&&r())};return k.jsx(G,{isPresent:g,initial:!d.current||t?void 0:!1,custom:o,presenceAffectsLayout:i,mode:n,root:M,onExitComplete:g?void 0:P,anchorX:y,children:h},f)})})};export{Y as A,J as C,N as M,O as S,Q as X};
