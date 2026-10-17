function o(t,e){return e?.find(n=>n.id===t.paymentMethodId)?.type==="card"?`/pay/${t.id}`:`/track/${t.id}`}function r(t,e){return t?`/track/${e}`:null}export{o as a,r};
