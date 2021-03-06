<template>
    <b-row>
        <b-col xl="6">
            <b-card header="Ships">
                <b-row>
                    <b-col cols="12" sm="6" lg="6">
                        <b-card :no-body="true" id="orga-stats-total-ships">
                            <b-card-body class="p-0 clearfix">
                                <i class="fas fa-space-shuttle bg-info p-4 font-2xl mr-3 float-left"></i>
                                <div class="h5 text-primary mb-0 pt-3">{{ totalShips }}</div>
                                <div class="text-muted text-uppercase font-weight-bold font-xs">Total ships</div>
                            </b-card-body>
                        </b-card>
                    </b-col>
                    <b-col cols="12" sm="6" lg="6">
                        <b-card :no-body="true" id="orga-stats-ships-status">
                            <b-card-body class="p-0 clearfix">
                                <i class="fas fa-calendar-check bg-warning p-4 font-2xl mr-3 float-left"></i>
                                <div class="h5 text-primary mb-0 pt-3">{{ countFlightReady }} / {{ countInConcept }}</div>
                                <div class="text-muted text-uppercase font-weight-bold font-xs">Flight ready / In concept</div>
                            </b-card-body>
                        </b-card>
                    </b-col>
                    <b-col cols="12" sm="6" lg="6">
                        <b-card :no-body="true" id="orga-stats-crew">
                            <b-card-body class="p-0 clearfix">
                                <i class="fas fa-user-friends bg-danger p-4 font-2xl mr-3 float-left"></i>
                                <div class="h5 text-primary mb-0 pt-3">{{ minCrew }} / {{ maxCrew }}</div>
                                <div class="text-muted text-uppercase font-weight-bold font-xs">Min crew / Max crew</div>
                            </b-card-body>
                        </b-card>
                    </b-col>
                    <b-col cols="12" sm="6" lg="6">
                        <b-card :no-body="true" id="orga-stats-cargo-capacity">
                            <b-card-body class="p-0 clearfix">
                                <i class="fas fa-boxes bg-primary p-4 font-2xl mr-3 float-left"></i>
                                <div class="h5 text-primary mb-0 pt-3">{{ cargoCapacity }}</div>
                                <div class="text-muted text-uppercase font-weight-bold font-xs">Cargo capacity (SCU)</div>
                            </b-card-body>
                        </b-card>
                    </b-col>
                </b-row>
                <ShipSizeDoughnut ref="shipSizeDoughnut" style="height: 350px;"/>
            </b-card>
        </b-col>
        <b-col xl="6">
            <b-card header="Citizens">
                <b-row>
                    <b-col cols="12" sm="6" lg="6">
                        <b-card :no-body="true" id="orga-stats-registered-total">
                            <b-card-body class="p-0 clearfix">
                                <i class="fas fa-users bg-info p-4 font-2xl mr-3 float-left"></i>
                                <div class="h5 text-primary mb-0 pt-3">{{ countCitizens }} / {{ totalMembers }}</div>
                                <div class="text-muted text-uppercase font-weight-bold font-xs">Registered / Total</div>
                            </b-card-body>
                        </b-card>
                    </b-col>
                    <b-col cols="12" sm="6" lg="6">
                        <b-card :no-body="true" id="orga-stats-average-ships">
                            <b-card-body class="p-0 clearfix">
                                <i class="fas fa-space-shuttle bg-warning p-4 font-2xl mr-3 float-left"></i>
                                <div class="h5 text-primary mb-0 pt-3">{{ averageShipsPerCitizen }}</div>
                                <div class="text-muted text-uppercase font-weight-bold font-xs">Average ships per citizen</div>
                            </b-card-body>
                        </b-card>
                    </b-col>
                    <b-col cols="12" sm="12" lg="12">
                        <b-card :no-body="true" id="orga-stats-ships-most-ships-citizen">
                            <b-card-body class="p-0 clearfix">
                                <i class="fas fa-medal bg-danger p-4 font-2xl mr-3 float-left"></i>
                                <div class="h5 text-primary mb-0 pt-3">
                                    <template v-if="citizenMostShips.citizen != null">{{ citizenMostShips.citizen.handle }} ({{ citizenMostShips.countShips }})</template>
                                    <template v-else><i>None</i></template>
                                </div>
                                <div class="text-muted text-uppercase font-weight-bold font-xs">Citizen with most ships</div>
                            </b-card-body>
                        </b-card>
                    </b-col>
                </b-row>
                <ShipPerCitizenBar ref="shipPerCitizenBar" style="height: 350px;"/>
            </b-card>
        </b-col>
    </b-row>
</template>

<script>
    import axios from 'axios';
    import ShipPerCitizenBar from '../charts/ShipPerCitizenBar';
    import ShipSizeDoughnut from '../charts/ShipSizeDoughnut';

    export default {
        name: 'OrgaStatistics',
        props: ['selectedSid'],
        components: {ShipPerCitizenBar, ShipSizeDoughnut},
        data() {
            return {
                // stats ships
                totalShips: 0,
                countFlightReady: 0,
                countInConcept: 0,
                minCrew: 0,
                maxCrew: 0,
                cargoCapacity: 0,
                // stats citizens
                countCitizens: 0,
                totalMembers: 0,
                averageShipsPerCitizen: 0,
                citizenMostShips: {citizen: null, countShips: 0},
            };
        },
        created() {
            this.findCitizenStatistics();
            this.findShipsStatistics();
        },
        methods: {
            findCitizenStatistics() {
                axios.get(`/api/organization/${this.selectedSid}/stats/citizens`).then(response => {
                    this.countCitizens = response.data.countCitizens;
                    this.totalMembers = response.data.totalMembers;
                    this.averageShipsPerCitizen = Math.round(response.data.averageShipsPerCitizen * 10) / 10;
                    this.citizenMostShips = response.data.citizenMostShips;
                    this.$refs.shipPerCitizenBar.setData({
                        xAxis: response.data.chartShipsPerCitizen.xAxis,
                        yAxis: response.data.chartShipsPerCitizen.yAxis,
                    })
                }).catch(err => {
                    if (err.response.status === 401) {
                        // not connected
                        return;
                    }
                    if (err.response.status === 404) {
                        // not exist
                        return;
                    }
                    if (err.response.data.errorMessage) {
                        this.$toastr.e(err.response.data.errorMessage);
                    } else {
                        this.$toastr.e('An error has occurred when retrieving citizen stats. Please try again later.');
                    }
                });
            },
            findShipsStatistics() {
                axios.get(`/api/organization/${this.selectedSid}/stats/ships`).then(response => {
                    this.totalShips = response.data.countShips;
                    this.countFlightReady = response.data.countFlightReady;
                    this.countInConcept = response.data.countInConcept;
                    this.minCrew = response.data.minCrew;
                    this.maxCrew = response.data.maxCrew;
                    this.cargoCapacity = response.data.cargoCapacity;
                    this.$refs.shipSizeDoughnut.setData({
                        xAxis: response.data.chartShipSizes.xAxis,
                        yAxis: response.data.chartShipSizes.yAxis,
                    });
                }).catch(err => {
                    if (err.response.status === 401) {
                        // not connected
                        return;
                    }
                    if (err.response.status === 404) {
                        // not exist
                        return;
                    }
                    if (err.response.data.errorMessage) {
                        this.$toastr.e(err.response.data.errorMessage);
                    } else {
                        this.$toastr.e('An error has occurred when retrieving ships stats. Please try again later.');
                    }
                });
            },
        },
    };
</script>
