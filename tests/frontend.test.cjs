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
