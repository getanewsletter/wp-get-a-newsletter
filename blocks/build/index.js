(()=>{"use strict";var e,t={74:()=>{const e=window.React,t=window.wp.blocks,l=window.wp.element,r=window.wp.blockEditor,a=window.wp.components,n=window.wp.i18n;(0,t.registerBlockType)("gan/newsletter-form",{title:(0,n.__)("Newsletter Form","getanewsletter"),icon:"email",category:"widgets",attributes:{formId:{type:"string",default:""},isTitleEnabled:{type:"boolean",default:!1},formTitle:{type:"string",default:"Join our newsletter"},isDescriptionEnabled:{type:"boolean",default:!1},formDescription:{type:"string",default:"Get weekly access to our deals, tricks and tips"},appearance:{type:"string",default:"square"},fieldBackground:{type:"string",default:"#ffffff"},fieldBorder:{type:"string",default:"#000000"},labelColor:{type:"string",default:"#000000"},buttonBackground:{type:"string",default:"#0280FF"},buttonTextColor:{type:"string",default:"#000000"},errorMessage:{type:"string",default:""}},edit:({attributes:t,setAttributes:o})=>{const[s,i]=(0,l.useState)([]),[c,m]=(0,l.useState)(null),[d,u]=(0,l.useState)(!0),[p,b]=(0,l.useState)(!1);(0,l.useEffect)((()=>{u(!0),fetch(`${ganAjax.ajaxurl}?action=gan_get_subscription_forms_list`).then((e=>e.json())).then((e=>{e.success?i(e.data):o({errorMessage:e.data}),u(!1)}))}),[]),(0,l.useEffect)((()=>{t.formId&&(b(!0),fetch(`${ganAjax.ajaxurl}?action=gan_get_subscription_form`,{method:"POST",body:new URLSearchParams({form_id:t.formId})}).then((e=>e.json())).then((e=>{e.success?m(e.data):o({errorMessage:e.data}),b(!1)})))}),[t.formId]),(0,l.useEffect)((()=>{const e=document.querySelector(".gan-newsletter-form");e&&(e.style.setProperty("--border-radius","rounded"===t.appearance?"8px":"0"),e.style.setProperty("--field-background",t.fieldBackground),e.style.setProperty("--field-border",t.fieldBorder),e.style.setProperty("--label-color",t.labelColor),e.style.setProperty("--button-background",t.buttonBackground),e.style.setProperty("--button-text-color",t.buttonTextColor))}),[t.appearance,t.fieldBackground,t.fieldBorder,t.labelColor,t.buttonBackground,t.buttonTextColor]);const f=e=>{o({formId:e})};return(0,e.createElement)(e.Fragment,null,(0,e.createElement)(r.InspectorControls,null,(0,e.createElement)(a.PanelBody,{title:(0,n.__)("Form Settings","getanewsletter")},d?(0,e.createElement)(a.Spinner,null):(0,e.createElement)(a.SelectControl,{label:(0,n.__)("Select Form","getanewsletter"),value:t.formId,options:s.map((e=>({label:e.name,value:e.key}))),onChange:f}),(0,e.createElement)(a.CheckboxControl,{label:(0,n.__)("Title","getanewsletter"),checked:t.isTitleEnabled,onChange:e=>o({isTitleEnabled:e})}),t.isTitleEnabled&&(0,e.createElement)(a.TextControl,{label:(0,n.__)("Title","getanewsletter"),value:t.formTitle,onChange:e=>o({formTitle:e}),placeholder:"Join our newsletter"}),(0,e.createElement)(a.CheckboxControl,{label:(0,n.__)("Description","getanewsletter"),checked:t.isDescriptionEnabled,onChange:e=>o({isDescriptionEnabled:e})}),t.isDescriptionEnabled&&(0,e.createElement)(a.TextControl,{label:(0,n.__)("Description","getanewsletter"),value:t.formDescription,onChange:e=>o({formDescription:e}),placeholder:"Get weekly access to our deals, tricks and tips"}),(0,e.createElement)(a.RadioControl,{label:(0,n.__)("Appearance","getanewsletter"),selected:t.appearance,options:[{label:(0,n.__)("Square","getanewsletter"),value:"square"},{label:(0,n.__)("Rounded","getanewsletter"),value:"rounded"}],onChange:e=>o({appearance:e})}),(0,e.createElement)(a.BaseControl,{label:(0,n.__)("Field Background","getanewsletter")},(0,e.createElement)(a.ColorPicker,{color:t.fieldBackground,onChangeComplete:e=>o({fieldBackground:e.hex})})),(0,e.createElement)(a.BaseControl,{label:(0,n.__)("Field Border","getanewsletter")},(0,e.createElement)(a.ColorPicker,{color:t.fieldBorder,onChangeComplete:e=>o({fieldBorder:e.hex})})),(0,e.createElement)(a.BaseControl,{label:(0,n.__)("Label Color","getanewsletter")},(0,e.createElement)(a.ColorPicker,{color:t.labelColor,onChangeComplete:e=>o({labelColor:e.hex})})),(0,e.createElement)(a.BaseControl,{label:(0,n.__)("Button Background","getanewsletter")},(0,e.createElement)(a.ColorPicker,{color:t.buttonBackground,onChangeComplete:e=>o({buttonBackground:e.hex})})),(0,e.createElement)(a.BaseControl,{label:(0,n.__)("Button Text Color","getanewsletter")},(0,e.createElement)(a.ColorPicker,{color:t.buttonTextColor,onChangeComplete:e=>o({buttonTextColor:e.hex})})))),(0,e.createElement)("div",{className:"gan-newsletter-form"},t.errorMessage&&(0,e.createElement)("div",{className:"error-message"},t.errorMessage),!t.formId&&(0,e.createElement)(e.Fragment,null,d?(0,e.createElement)(a.Spinner,null):(0,e.createElement)(a.SelectControl,{label:(0,n.__)("Select Form","getanewsletter"),value:t.formId,options:s.map((e=>({label:e.name,value:e.key}))),onChange:f})),t.isTitleEnabled&&(0,e.createElement)("h2",null,t.formTitle),t.isDescriptionEnabled&&(0,e.createElement)("p",null,t.formDescription),p?(0,e.createElement)(a.Spinner,null):c&&(0,e.createElement)("form",{method:"post",className:"newsletter-signup",action:"javascript:alert('success!');",enctype:"multipart/form-data"},(0,e.createElement)("input",{type:"hidden",name:"action",value:"getanewsletter_subscribe"}),c.form.first_name&&(0,e.createElement)("p",null,(0,e.createElement)("label",{htmlFor:"id_first_name"},c.form.first_name_label||(0,n.__)("First name","getanewsletter")),(0,e.createElement)("br",null),(0,e.createElement)("input",{id:"id_first_name",type:"text",className:"text",name:"id_first_name"})),c.form.last_name&&(0,e.createElement)("p",null,(0,e.createElement)("label",{htmlFor:"id_last_name"},c.form.last_name_label||(0,n.__)("Last name","getanewsletter")),(0,e.createElement)("br",null),(0,e.createElement)("input",{id:"id_last_name",type:"text",className:"text",name:"id_last_name"})),(0,e.createElement)("p",null,(0,e.createElement)("label",{htmlFor:"id_email"},(0,n.__)("E-mail","getanewsletter")),(0,e.createElement)("br",null),(0,e.createElement)("input",{id:"id_email",type:"text",className:"text",name:"id_email"})),c.customAttributes.map((t=>c.form.attributes.includes(t.code)&&(0,e.createElement)("p",{key:t.code},(0,e.createElement)("label",{htmlFor:`attr_${t.code}`},t.name),(0,e.createElement)("br",null),(0,e.createElement)("input",{id:`attr_${t.code}`,type:"text",className:"text",name:`attributes[${t.code}]`})))),(0,e.createElement)("p",null,(0,e.createElement)("input",{type:"hidden",name:"form_link",value:c.form.form_link,id:"id_form_link"}),(0,e.createElement)("input",{type:"hidden",name:"key",value:c.form.key,id:"id_key"}),(0,e.createElement)(a.Button,{type:"submit"},c.form.button_text||(0,n.__)("Subscribe","getanewsletter")))),(0,e.createElement)("div",{className:"news-note"})))},save:()=>null})}},l={};function r(e){var a=l[e];if(void 0!==a)return a.exports;var n=l[e]={exports:{}};return t[e](n,n.exports,r),n.exports}r.m=t,e=[],r.O=(t,l,a,n)=>{if(!l){var o=1/0;for(m=0;m<e.length;m++){for(var[l,a,n]=e[m],s=!0,i=0;i<l.length;i++)(!1&n||o>=n)&&Object.keys(r.O).every((e=>r.O[e](l[i])))?l.splice(i--,1):(s=!1,n<o&&(o=n));if(s){e.splice(m--,1);var c=a();void 0!==c&&(t=c)}}return t}n=n||0;for(var m=e.length;m>0&&e[m-1][2]>n;m--)e[m]=e[m-1];e[m]=[l,a,n]},r.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),(()=>{var e={57:0,350:0};r.O.j=t=>0===e[t];var t=(t,l)=>{var a,n,[o,s,i]=l,c=0;if(o.some((t=>0!==e[t]))){for(a in s)r.o(s,a)&&(r.m[a]=s[a]);if(i)var m=i(r)}for(t&&t(l);c<o.length;c++)n=o[c],r.o(e,n)&&e[n]&&e[n][0](),e[n]=0;return r.O(m)},l=globalThis.webpackChunkgetanewsletter_blocks=globalThis.webpackChunkgetanewsletter_blocks||[];l.forEach(t.bind(null,0)),l.push=t.bind(null,l.push.bind(l))})();var a=r.O(void 0,[350],(()=>r(74)));a=r.O(a)})();