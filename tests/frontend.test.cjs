'use strict';
const {test} = require('node:test');
const assert = require('node:assert/strict');
const {validCoordinates, safeText, mapURL} = require('../meu-ip-info.js');

test('validates numeric latitude and longitude bounds', () => {
  assert.equal(validCoordinates(-12.97, -38.5), true);
  assert.equal(validCoordinates(90, 180), true);
  for(const coords of [[91,0],[0,-181],[null,2],['-12',2],[NaN,4],[Infinity,0]]) {
    assert.equal(validCoordinates(...coords),false);
  }
});
test('map URLs use only validated coordinates and OpenStreetMap', () => {
  const url = new URL(mapURL(-12.97,-38.5));
  assert.equal(url.hostname,'www.openstreetmap.org');
  assert.equal(url.searchParams.get('marker'),'-12.97,-38.5');
  assert.equal(mapURL(200,0),null);
});
test('map bounds are clamped to the valid coordinate ranges', () => {
  const u=new URL(mapURL(90,180));
  assert.equal(u.searchParams.get('bbox').split(',')[2],'180');
});
test('non-string data is hidden rather than injected as HTML', () => {
  assert.equal(safeText({html:'<script>alert(1)</script>'}),'Não disponível');
  assert.equal(safeText(''),'Não disponível');
  assert.equal(safeText('Example ISP'),'Example ISP');
});

test('consent controls delay lookup and external map for each shortcode instance', async () => {
  const vm = require('node:vm');
  const fs = require('node:fs');
  const path = require('node:path');
  const source = fs.readFileSync(path.join(__dirname,'..','meu-ip-info.js'),'utf8');
  let requests = 0;
  let mapsCreated = 0;
  function makeButton() {
    const listeners = {};
    return {listeners,hidden:false,disabled:false,
      addEventListener:(event,callback)=>{listeners[event]=callback;}};
  }
  function makeWidget() {
    const lookup=makeButton(),copy=makeButton(),map=makeButton();
    const status={textContent:''}, elements={};
    for(const name of ['ip','org','country','region','city'])elements[name]={textContent:''};
    const details={hidden:true,querySelector:(selector)=>elements[selector.match(/data-field="([^"]+)"/)?.[1]]};
    const container={hidden:true,children:[],replaceChildren(...items){this.children=items;}};
    const fields={'.mii-lookup':lookup,'.mii-copy':copy,'.mii-map':map,
      '.mii-status':status,'.mii-details':details,'.mii-map-container':container};
    return {lookup,copy,map,status,details,container,elements,dataset:{},
      getAttribute:()=> 'https://example.test/wp-json/meu-ip-info/v1/lookup',
      querySelector:(selector)=>fields[selector]};
  }
  const first=makeWidget(),second=makeWidget();
  const document={
    readyState:'complete',
    querySelectorAll:()=>[first,second],
    createElement:(name)=>{
      assert.equal(name,'iframe');
      mapsCreated++;
      return {setAttribute(){},src:'',title:'',referrerPolicy:'',loading:''};
    }
  };
  const fakeFetch=async() => {
    requests++;
    return {ok:true,json:async()=>({ip:'8.8.8.8',org:'Example',country:'BR',
      region:'Bahia',city:'Salvador',latitude:-12.97,longitude:-38.5})};
  };
  vm.runInNewContext(source,{document,fetch:fakeFetch,URL,
    navigator:{clipboard:{writeText:async()=>{}}}}, {timeout:1000});
  assert.equal(requests,0,'Lookup must not run at page load');
  assert.equal(mapsCreated,0,'Map must not load at page load');
  await first.lookup.listeners.click();
  assert.equal(requests,1);
  assert.equal(first.elements.ip.textContent,'8.8.8.8');
  assert.equal(second.details.hidden,true,'Second widget must be independent');
  assert.equal(mapsCreated,0,'Lookup must not load external map');
  first.map.listeners.click();
  assert.equal(mapsCreated,1);
  assert.equal(first.container.hidden,false);
  assert.match(first.container.children[0].src,/openstreetmap.org/);
  assert.equal(first.container.children[0].referrerPolicy,'no-referrer');
  await second.lookup.listeners.click();
  assert.equal(requests,2,'Second widget did not call REST endpoint independently');
  assert.equal(second.elements.city.textContent,'Salvador');
});
